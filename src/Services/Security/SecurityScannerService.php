<?php

namespace SoyCristianRico\LaravelServerMonitor\Services\Security;

class SecurityScannerService
{
    public function checkSuspiciousProcesses(): ?array
    {
        $suspiciousProcesses = shell_exec("ps aux | grep -E '(wget|curl).*\.sh' | grep -v grep");

        if (! empty(trim($suspiciousProcesses))) {
            return [
                'type' => 'Suspicious Processes',
                'details' => $suspiciousProcesses,
            ];
        }

        return null;
    }

    public function checkSuspiciousPorts(): array
    {
        $alerts = [];

        $suspiciousPorts = shell_exec("netstat -tulpn 2>/dev/null | grep -E '(4444|5555|31337|12345)' | grep -v '127.0.0.1'");
        if (! empty(trim($suspiciousPorts))) {
            $alerts[] = [
                'type' => 'Suspicious Network Ports',
                'details' => $suspiciousPorts,
            ];
        }

        $scrapydPorts = shell_exec("netstat -tulpn 2>/dev/null | grep -E '(6800|6801)' | grep -v '127.0.0.1'");
        if (! empty(trim($scrapydPorts))) {
            $alerts[] = [
                'type' => 'Scrapyd Service Exposed',
                'details' => $scrapydPorts,
            ];
        }

        return $alerts;
    }

    public function checkDiskUsage(int $threshold = 90): ?array
    {
        $diskUsage = shell_exec("df -h | grep -E '{$threshold}[0-9]%|100%'");

        if (! empty(trim($diskUsage))) {
            return [
                'type' => 'High Disk Usage',
                'details' => $diskUsage,
            ];
        }

        return null;
    }

    public function checkCrontabModifications(string $timeFrame = '-1'): ?array
    {
        $modifiedCrontabs = shell_exec("find /var/spool/cron/crontabs /etc/cron* -type f -mtime $timeFrame 2>/dev/null");

        if (! empty(trim($modifiedCrontabs))) {
            return [
                'type' => 'Recently Modified Crontabs',
                'details' => $modifiedCrontabs,
            ];
        }

        return null;
    }

    public function checkFailedLogins(int $threshold = 20): ?array
    {
        $failedLogins = shell_exec("grep 'Failed password' /var/log/auth.log 2>/dev/null | tail -50 | wc -l");

        if (intval($failedLogins) > $threshold) {
            $failedDetails = shell_exec("grep 'Failed password' /var/log/auth.log 2>/dev/null | tail -20");

            return [
                'type' => 'High Failed Login Attempts',
                'details' => "Count: $failedLogins\nRecent attempts:\n$failedDetails",
            ];
        }

        return null;
    }

    public function checkNewUsers(int $days = 7): ?array
    {
        $newUsers = shell_exec("find /home -maxdepth 1 -type d -mtime -{$days} | grep -v '/home$'");

        if (! empty(trim($newUsers))) {
            $whitelistedUsers = config('server-monitor.security.whitelisted_users', []);
            $whitelistedDirs = config('server-monitor.security.whitelisted_directories', []);

            $filteredUsers = array_filter(explode("\n", trim($newUsers)), function ($userDir) use ($whitelistedUsers, $whitelistedDirs) {
                $userDir = trim($userDir);
                if (empty($userDir)) {
                    return false;
                }

                if (in_array($userDir, $whitelistedDirs)) {
                    return false;
                }

                $username = basename($userDir);
                if (in_array($username, $whitelistedUsers)) {
                    return false;
                }

                return true;
            });

            if (! empty($filteredUsers)) {
                $cacheKey = 'new_users_'.md5(implode('|', $filteredUsers));

                if ($this->hasUsersChanged($cacheKey, $filteredUsers)) {
                    $this->storeUserList($cacheKey, $filteredUsers);

                    return [
                        'type' => 'Recently Created Users',
                        'details' => implode("\n", $filteredUsers),
                    ];
                }
            }
        }

        return null;
    }

    public function checkModifiedSystemFiles(int $days = 1): ?array
    {
        $modifiedFiles = shell_exec("find /etc -type f -mtime -{$days} 2>/dev/null | grep -E '(passwd|shadow|sudoers|crontab|ssh)'");

        if (! empty(trim($modifiedFiles))) {
            return [
                'type' => 'Modified System Files',
                'details' => $modifiedFiles,
            ];
        }

        return null;
    }

    public function checkUnauthorizedSSHKeys(int $days = 7): ?array
    {
        $sshKeysModified = shell_exec("find /home -name 'authorized_keys*' -mtime -{$days} 2>/dev/null");

        if (! empty(trim($sshKeysModified))) {
            return [
                'type' => 'Recently Modified SSH Keys',
                'details' => $sshKeysModified,
            ];
        }

        return null;
    }

    public function checkLargeFiles(string $size = '100M', int $days = 1): ?array
    {
        $largeFiles = shell_exec("find /tmp /var/tmp /home -type f -size +{$size} -mtime -{$days} 2>/dev/null | head -20");

        if (! empty(trim($largeFiles))) {
            return [
                'type' => 'Large Files Created Recently',
                'details' => $largeFiles,
            ];
        }

        return null;
    }

    public function checkMalwarePatterns(): array
    {
        $alerts = [];
        $appPath = base_path();
        $publicPath = public_path();

        $directories = [
            $appPath,
            $publicPath,
        ];

        if (function_exists('storage_path')) {
            $directories[] = storage_path('app/public');
        }

        $excludedPaths = config('server-monitor.security.excluded_paths', []);
        $whitelistedFiles = config('server-monitor.security.whitelisted_security_files', []);

        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $allPhpFiles = shell_exec("find $dir -name '*.php' -type f 2>/dev/null");

            if (empty(trim($allPhpFiles))) {
                continue;
            }

            $phpFiles = $this->filterExcludedPaths($allPhpFiles, $excludedPaths);

            if (empty($phpFiles)) {
                continue;
            }

            $grepCommand = 'grep -l -E \'(eval\s*\(\s*\$|base64_decode\s*\(\s*\$|\$\w+\s*=\s*base64_decode|shell_exec\s*\(\s*\$|system\s*\(\s*\$|exec\s*\(\s*\$|passthru\s*\(\s*\$|popen\s*\(\s*\$|proc_open\s*\(\s*\$|file_get_contents\s*\(\s*\$|\$_[A-Z]+\s*\[\s*["\'][^"\']*["\'])\' '.implode(' ', array_map('escapeshellarg', $phpFiles)).' 2>/dev/null';

            $suspiciousPatterns = shell_exec($grepCommand);

            if (! empty(trim($suspiciousPatterns))) {
                $filteredFiles = $this->filterWhitelistedFiles($suspiciousPatterns, $whitelistedFiles);

                if (! empty($filteredFiles)) {
                    $cacheKey = 'malware_patterns_'.md5($dir);

                    if ($this->hasFilesChanged($cacheKey, $filteredFiles)) {
                        $alerts[] = [
                            'type' => 'Suspicious PHP Code Patterns',
                            'details' => "Directory: $dir\nFiles:\n".implode("\n", $filteredFiles),
                        ];

                        $this->storeFileList($cacheKey, $filteredFiles);
                    }
                }
            }

            $recentFiles = shell_exec("find $dir -type f \\( -name '*.php' -o -name '*.phtml' -o -name '*.php3' -o -name '*.php4' -o -name '*.php5' \\) -mtime -1 2>/dev/null");

            if (! empty(trim($recentFiles))) {
                $filteredRecentFiles = $this->filterExcludedPaths($recentFiles, $excludedPaths);

                if (! empty($filteredRecentFiles)) {
                    $filteredUploads = $this->filterWhitelistedFiles(implode("\n", $filteredRecentFiles), $whitelistedFiles);

                    if (! empty($filteredUploads)) {
                        $cacheKey = 'recent_uploads_'.md5($dir);

                        if ($this->hasFilesChanged($cacheKey, $filteredUploads)) {
                            $alerts[] = [
                                'type' => 'Recently Uploaded PHP Files',
                                'details' => "Directory: $dir\nFiles:\n".implode("\n", array_slice($filteredUploads, 0, 20)),
                            ];

                            $this->storeFileList($cacheKey, $filteredUploads);
                        }
                    }
                }
            }
        }

        return $alerts;
    }

    public function getListeningPorts(): string
    {
        return shell_exec("netstat -tulpn 2>/dev/null | grep LISTEN | grep -v '127.0.0.1' | grep -v '::1'") ?? '';
    }

    private function filterExcludedPaths(string $files, array $excludedPaths): array
    {
        $fileList = array_filter(explode("\n", trim($files)));

        return array_filter($fileList, function ($file) use ($excludedPaths) {
            foreach ($excludedPaths as $path) {
                if (strpos($file, '/'.$path.'/') !== false || strpos($file, '/'.$path) !== false) {
                    return false;
                }
            }

            return true;
        });
    }

    private function filterWhitelistedFiles(string $files, array $whitelistedFiles): array
    {
        $fileList = array_filter(explode("\n", trim($files)));
        $basePath = base_path();

        return array_filter($fileList, function ($file) use ($whitelistedFiles, $basePath) {
            $relativePath = str_replace($basePath.'/', '', $file);

            return ! in_array($relativePath, $whitelistedFiles);
        });
    }

    private function hasFilesChanged(string $cacheKey, array $currentFiles): bool
    {
        $cacheFile = $this->getCacheFilePath($cacheKey.'.json');

        if (! file_exists($cacheFile)) {
            return true;
        }

        $cachedData = json_decode(file_get_contents($cacheFile), true);

        if (! $cachedData || ! isset($cachedData['files'])) {
            return true;
        }

        sort($currentFiles);
        $cachedFiles = $cachedData['files'];
        sort($cachedFiles);

        return $currentFiles !== $cachedFiles;
    }

    private function storeFileList(string $cacheKey, array $files): void
    {
        $cacheDir = $this->getCacheDir();

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $data = [
            'timestamp' => time(),
            'files' => $files,
        ];

        file_put_contents($this->getCacheFilePath($cacheKey.'.json'), json_encode($data, JSON_PRETTY_PRINT));
    }

    private function getCacheDir(): string
    {
        return function_exists('storage_path')
            ? storage_path('security_cache')
            : sys_get_temp_dir().'/laravel_server_monitor_cache';
    }

    private function getCacheFilePath(string $filename): string
    {
        return $this->getCacheDir().'/'.$filename;
    }

    private function hasUsersChanged(string $cacheKey, array $currentUsers): bool
    {
        $cacheFile = $this->getCacheFilePath($cacheKey.'.json');

        if (! file_exists($cacheFile)) {
            return true;
        }

        $cachedData = json_decode(file_get_contents($cacheFile), true);

        if (! $cachedData || ! isset($cachedData['users'])) {
            return true;
        }

        sort($currentUsers);
        $cachedUsers = $cachedData['users'];
        sort($cachedUsers);

        // If users are different, always alert
        if ($currentUsers !== $cachedUsers) {
            return true;
        }

        // If users are the same, check cooldown
        $alertCooldown = config('server-monitor.security.alert_cooldown', 120);
        $cacheAge = time() - $cachedData['timestamp'];

        return $cacheAge >= ($alertCooldown * 60);
    }

    private function storeUserList(string $cacheKey, array $users): void
    {
        $cacheDir = $this->getCacheDir();

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $data = [
            'timestamp' => time(),
            'users' => $users,
        ];

        file_put_contents($this->getCacheFilePath($cacheKey.'.json'), json_encode($data, JSON_PRETTY_PRINT));
    }
}