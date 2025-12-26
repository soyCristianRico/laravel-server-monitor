<?php

namespace SoyCristianRico\LaravelServerMonitor\Services\Security;

class SecurityScannerService
{
    public function checkSuspiciousProcesses(): ?array
    {
        // ENHANCED: Check original + new suspicious patterns in ONE check
        $suspiciousProcesses = shell_exec("ps aux | grep -E '(wget|curl).*\.sh' | grep -v grep");

        if (! empty(trim($suspiciousProcesses))) {
            return [
                'type' => 'Suspicious Processes',
                'details' => $suspiciousProcesses,
            ];
        }

        return null;
    }

    public function checkSuspiciousPhpProcesses(): ?array
    {
        $alerts = [];

        $alerts = array_merge($alerts, $this->checkPhpProcessesInTempDirectories());
        $alerts = array_merge($alerts, $this->checkPhpProcessesWithSuspiciousFileNames());
        $alerts = array_merge($alerts, $this->checkPhpProcessesOutsideWebDirectories());

        return empty($alerts) ? null : $alerts;
    }

    private function checkPhpProcessesInTempDirectories(): array
    {
        $tmpPhpProcesses = shell_exec("ps aux | grep -E 'php.*-f.*/(tmp|var/tmp)/' | grep -v grep");

        if (empty(trim($tmpPhpProcesses))) {
            return [];
        }

        return [[
            'type' => 'Suspicious PHP Processes in /tmp',
            'details' => $tmpPhpProcesses,
        ]];
    }

    private function checkPhpProcessesWithSuspiciousFileNames(): array
    {
        $suspiciousPhpProcesses = shell_exec("ps aux | grep -E 'php.*-f.*(httpd\.conf|apache\.conf|nginx\.conf|\.cache|\.log)' | grep -v grep");

        if (empty(trim($suspiciousPhpProcesses))) {
            return [];
        }

        return [[
            'type' => 'PHP Processes with Suspicious File Names',
            'details' => $suspiciousPhpProcesses,
        ]];
    }

    private function checkPhpProcessesOutsideWebDirectories(): array
    {
        $outsidePhpProcesses = shell_exec("ps aux | grep -E 'php.*-f' | grep -v -E '(/home/[^/]+/(www|public_html|laravel|app)|/var/www)' | grep -v 'php-fpm' | grep -v grep");

        if (empty(trim($outsidePhpProcesses))) {
            return [];
        }

        return [[
            'type' => 'PHP Processes Outside Web Directories',
            'details' => $outsidePhpProcesses,
        ]];
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

            // ENHANCED: More comprehensive malware pattern detection
            $malwarePatterns = [
                'eval\s*\(\s*base64_decode\s*\(',
                'eval\s*\(\s*gzinflate\s*\(',
                'eval\s*\(\s*gzuncompress\s*\(',
                'eval\s*\(\s*str_rot13\s*\(',
                'eval\s*\(\s*\$_REQUEST\[',
                'eval\s*\(\s*\$_POST\[',
                'eval\s*\(\s*\$_GET\[',
                'eval\s*\(\s*\$_COOKIE\[',
                '\$_REQUEST\[["\']id["\']\]',
                '\$_COOKIE\[["\']d["\']\]',
                'md5\s*\(\s*\$_COOKIE',
                'goto\s+[A-Za-z]{10,}',
                '\\x[0-9a-f]{2}.*\\x[0-9a-f]{2}',
                '@eval\s*\(',
                'assert\s*\(\s*base64_decode',
                'preg_replace\s*\(\s*["\'][^"\']*e["\']',
                'create_function\s*\(',
                'file_get_contents\s*\(\s*["\']php://input["\']',
                'move_uploaded_file.*\.php["\']',
                'copy\s*\(\s*\$_FILES',
                'shell_exec\s*\(\s*base64_decode',
                'system\s*\(\s*base64_decode',
                'passthru\s*\(\s*base64_decode',
                'chr\s*\(\s*\d+\s*\)\s*\.\s*chr\s*\(',
                'function_exists\s*\(\s*["\']eval["\']',
            ];

            $grepPattern = implode('|', $malwarePatterns);
            $grepCommand = 'grep -l -E \'(' . $grepPattern . ')\' '.implode(' ', array_map('escapeshellarg', $phpFiles)).' 2>/dev/null';

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

    public function checkSuspiciousUploads(): array
    {
        $alerts = [];

        $alerts = array_merge($alerts, $this->scanPhpFilesInStorageDirectories());
        $alerts = array_merge($alerts, $this->scanPhpFilesInUploadDirectories());
        $alerts = array_merge($alerts, $this->scanUnexpectedPhpFilesInPublicRoot());
        $alerts = array_merge($alerts, $this->scanPhpFilesWithRandomNames());
        $alerts = array_merge($alerts, $this->scanSuspiciousAssetsDirectories());
        $alerts = array_merge($alerts, $this->scanSuspiciousVendorDirectories());
        $alerts = array_merge($alerts, $this->scanPhpFilesInVendorPaths());

        return $alerts;
    }

    private function scanPhpFilesInStorageDirectories(): array
    {
        $basePath = base_path();
        $storagePhpFiles = shell_exec("find {$basePath}/storage/app/public -name '*.php' -o -name '*.phtml' -o -name '*.php3' -o -name '*.php4' -o -name '*.php5' 2>/dev/null");

        if (empty(trim($storagePhpFiles))) {
            return [];
        }

        return [[
            'type' => 'PHP Files in Storage Directory',
            'details' => "PHP files found in storage/app/public:\n" . $storagePhpFiles,
        ]];
    }

    private function scanPhpFilesInUploadDirectories(): array
    {
        $basePath = base_path();
        $publicUploads = shell_exec("find {$basePath}/public -path '*/uploads/*' -name '*.php' -o -path '*/images/*' -name '*.php' -o -path '*/files/*' -name '*.php' 2>/dev/null");

        if (empty(trim($publicUploads))) {
            return [];
        }

        return [[
            'type' => 'PHP Files in Upload Directories',
            'details' => "PHP files found in upload directories:\n" . $publicUploads,
        ]];
    }

    private function scanUnexpectedPhpFilesInPublicRoot(): array
    {
        $basePath = base_path();
        $publicPhpFiles = shell_exec("find {$basePath}/public -maxdepth 1 -name '*.php' ! -name 'index.php' 2>/dev/null");

        if (empty(trim($publicPhpFiles))) {
            return [];
        }

        return [[
            'type' => 'Suspicious PHP Files in Public Root',
            'details' => "Unexpected PHP files in public/:\n" . $publicPhpFiles,
        ]];
    }

    private function scanPhpFilesWithRandomNames(): array
    {
        $basePath = base_path();
        $randomNamedPhp = shell_exec("find {$basePath}/public -name '*.php' | grep -E '/[a-f0-9]{8,}\.php$|/[a-zA-Z0-9]{10,}\.php$' 2>/dev/null");

        if (empty(trim($randomNamedPhp))) {
            return [];
        }

        return [[
            'type' => 'PHP Files with Random Names',
            'details' => "PHP files with suspicious random names:\n" . $randomNamedPhp,
        ]];
    }

    private function scanSuspiciousAssetsDirectories(): array
    {
        $basePath = base_path();
        $suspiciousAssets = shell_exec("find {$basePath} -type d -name 'assets' -path '*/config/*' -o -name 'assets' -path '*/app/*' -o -name 'assets' -path '*/routes/*' -o -name 'assets' -path '*/database/*' -o -name 'assets' -path '*/bootstrap/*' 2>/dev/null");

        if (empty(trim($suspiciousAssets))) {
            return [];
        }

        return [[
            'type' => 'Suspicious Assets Directories',
            'details' => "Unexpected assets/ directories found:\n" . $suspiciousAssets,
        ]];
    }

    private function scanSuspiciousVendorDirectories(): array
    {
        $basePath = base_path();
        // Find directories but exclude legitimate Symfony Resources/assets paths
        $suspiciousVendorDirs = shell_exec("find {$basePath}/vendor -type d \\( -name 'assets' -o -name 'images' -o -name 'uploads' \\) 2>/dev/null | grep -v '/Resources/assets'");

        if (empty(trim($suspiciousVendorDirs))) {
            return [];
        }

        return [[
            'type' => 'Suspicious Vendor Directories',
            'details' => "Unexpected directories in vendor/:\n" . $suspiciousVendorDirs,
        ]];
    }

    private function scanPhpFilesInVendorPaths(): array
    {
        $basePath = base_path();
        // Look for PHP files in suspicious vendor paths but exclude legitimate Symfony Resources
        $suspiciousVendorPhp = shell_exec("find {$basePath}/vendor \\( -name '*.php' -path '*/assets/*' -o -name '*.php' -path '*/images/*' -o -name '*.php' -path '*/uploads/*' \\) 2>/dev/null | grep -v '/Resources/assets'");

        if (empty(trim($suspiciousVendorPhp))) {
            return [];
        }

        // Special check for .svg.php files which are highly suspicious
        $svgPhpFiles = [];
        $allFiles = explode("\n", trim($suspiciousVendorPhp));
        foreach ($allFiles as $file) {
            if (strpos($file, '.svg.php') !== false) {
                $svgPhpFiles[] = $file;
            }
        }

        if (!empty($svgPhpFiles)) {
            return [[
                'type' => 'CRITICAL: Suspicious .svg.php Files in Vendor',
                'details' => "Highly suspicious .svg.php files found (possible malware):\n" . implode("\n", $svgPhpFiles),
            ]];
        }

        return [[
            'type' => 'PHP Files in Suspicious Vendor Paths',
            'details' => "PHP files in unusual vendor/ locations:\n" . $suspiciousVendorPhp,
        ]];
    }

    public function checkSuspiciousHtaccess(): array
    {
        $alerts = [];

        $alerts = array_merge($alerts, $this->scanHtaccessFilesInPublicSubdirectories());
        $alerts = array_merge($alerts, $this->scanHtaccessFilesOutsidePublic());

        return $alerts;
    }

    private function scanHtaccessFilesInPublicSubdirectories(): array
    {
        $basePath = base_path();
        $suspiciousHtaccess = shell_exec("find {$basePath}/public -name '.htaccess' ! -path '{$basePath}/public/.htaccess' 2>/dev/null");

        if (empty(trim($suspiciousHtaccess))) {
            return [];
        }

        return [[
            'type' => 'Suspicious .htaccess Files',
            'details' => "Unexpected .htaccess files found:\n" . $suspiciousHtaccess,
        ]];
    }

    private function scanHtaccessFilesOutsidePublic(): array
    {
        $basePath = base_path();
        // Find .htaccess files outside public but exclude vendor and node_modules
        $outsideHtaccess = shell_exec("find {$basePath} -name '.htaccess' ! -path '{$basePath}/public/*' ! -path '*/vendor/*' ! -path '*/node_modules/*' 2>/dev/null | head -20");

        if (empty(trim($outsideHtaccess))) {
            return [];
        }

        // Filter out legitimate protective .htaccess files
        $htaccessFiles = array_filter(explode("\n", trim($outsideHtaccess)));
        $suspiciousFiles = [];

        foreach ($htaccessFiles as $file) {
            if (empty($file)) {
                continue;
            }

            // Check if this is a protective .htaccess in storage/app/public
            if (strpos($file, '/storage/app/public') !== false) {
                // Read the file content to check if it's a protective .htaccess
                if (file_exists($file)) {
                    $content = file_get_contents($file);
                    // If it contains "Deny from all" or "Require all denied", it's protective
                    if (stripos($content, 'deny from all') !== false ||
                        stripos($content, 'require all denied') !== false ||
                        stripos($content, 'order allow,deny') !== false) {
                        // This is a legitimate protective .htaccess, skip it
                        continue;
                    }
                }
            }

            // Check against whitelisted .htaccess paths
            $relativePath = str_replace($basePath . '/', '', $file);
            $whitelistedPaths = config('server-monitor.security.whitelisted_htaccess', []);

            if (in_array($relativePath, $whitelistedPaths)) {
                continue;
            }

            $suspiciousFiles[] = $file;
        }

        if (empty($suspiciousFiles)) {
            return [];
        }

        return [[
            'type' => '.htaccess Files Outside Public',
            'details' => "Found potentially suspicious .htaccess files:\n" . implode("\n", $suspiciousFiles),
        ]];
    }

    public function checkFakeImageFiles(): array
    {
        $alerts = [];
        $basePath = base_path();

        // NEW: Check for image files that are actually PHP/text
        $fakeImages = shell_exec("find {$basePath}/public {$basePath}/storage/app/public -type f \\( -name '*.jpg' -o -name '*.jpeg' -o -name '*.png' -o -name '*.gif' -o -name '*.webp' \\) -exec file {} \\; 2>/dev/null | grep -E '(PHP script|ASCII text|UTF-8 Unicode text)' | cut -d: -f1");

        if (! empty(trim($fakeImages))) {
            $fakeImageList = array_filter(explode("\n", trim($fakeImages)));

            // Check if any of these files contain PHP code
            $phpInFakeImages = [];
            foreach ($fakeImageList as $file) {
                $content = file_get_contents($file);
                if ($content && (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false || preg_match('/eval\s*\(/i', $content))) {
                    $phpInFakeImages[] = $file;
                }
            }

            if (!empty($phpInFakeImages)) {
                $alerts[] = [
                    'type' => 'Fake Image Files Containing PHP',
                    'details' => "Image files that contain PHP code:\n" . implode("\n", $phpInFakeImages),
                ];
            }
        }

        return $alerts;
    }

    public function checkFileIntegrity(): array
    {
        $alerts = [];
        $basePath = base_path();

        // NEW: Check integrity of critical Laravel files
        $criticalFiles = [
            $basePath . '/public/index.php',
            $basePath . '/bootstrap/app.php',
            $basePath . '/artisan',
        ];

        foreach ($criticalFiles as $file) {
            if (file_exists($file)) {
                $cacheKey = 'file_integrity_' . md5($file);
                $currentHash = hash_file('sha256', $file);

                $storedHash = $this->getStoredFileHash($cacheKey);

                if ($storedHash && $storedHash !== $currentHash) {
                    $alerts[] = [
                        'type' => 'Critical File Modified',
                        'details' => "File: $file\nPrevious hash: $storedHash\nCurrent hash: $currentHash",
                    ];
                }

                // Store current hash for future comparisons
                $this->storeFileHash($cacheKey, $currentHash);
            }
        }

        return $alerts;
    }

    private function getStoredFileHash(string $cacheKey): ?string
    {
        $cacheFile = $this->getCacheFilePath($cacheKey . '.hash');

        if (file_exists($cacheFile)) {
            return trim(file_get_contents($cacheFile));
        }

        return null;
    }

    private function storeFileHash(string $cacheKey, string $hash): void
    {
        $cacheDir = $this->getCacheDir();

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($this->getCacheFilePath($cacheKey . '.hash'), $hash);
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
        if ($this->isCacheMissing($cacheKey)) {
            return true;
        }

        $cachedData = $this->getCachedUserData($cacheKey);

        if ($this->isCacheDataInvalid($cachedData)) {
            return true;
        }

        if ($this->areUsersDifferent($currentUsers, $cachedData['users'])) {
            return true;
        }

        return $this->isCooldownExpired($cachedData['timestamp']);
    }

    private function isCacheMissing(string $cacheKey): bool
    {
        return ! file_exists($this->getCacheFilePath($cacheKey.'.json'));
    }

    private function getCachedUserData(string $cacheKey): ?array
    {
        $cacheFile = $this->getCacheFilePath($cacheKey.'.json');

        return json_decode(file_get_contents($cacheFile), true);
    }

    private function isCacheDataInvalid(?array $cachedData): bool
    {
        return ! $cachedData || ! isset($cachedData['users']);
    }

    private function areUsersDifferent(array $currentUsers, array $cachedUsers): bool
    {
        sort($currentUsers);
        sort($cachedUsers);

        return $currentUsers !== $cachedUsers;
    }

    private function isCooldownExpired(int $cacheTimestamp): bool
    {
        $alertCooldown = config('server-monitor.security.alert_cooldown', 120);
        $cacheAge = time() - $cacheTimestamp;

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