<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

class DectaDebugCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'decta:debug
                            {--test-path= : Test a specific path}
                            {--detailed : Show detailed filesystem information}';

    /**
     * The console command description.
     */
    protected $description = 'Debug Decta file system and storage issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Decta Debug Information ===');
        $this->newLine();

        // Check configuration
        $this->checkConfiguration();
        $this->newLine();

        // Check Laravel filesystem configuration
        $this->checkFilesystemConfiguration();
        $this->newLine();

        // Check storage setup
        $this->checkStorageSetup();
        $this->newLine();

        // Test directory creation
        $this->testDirectoryCreation();
        $this->newLine();

        // Test file operations with detailed analysis
        $this->testFileOperationsDetailed();
        $this->newLine();

        // Check specific path if provided
        $testPath = $this->option('test-path');
        if ($testPath) {
            $this->testSpecificPath($testPath);
            $this->newLine();
        }

        $this->info('=== Debug Complete ===');
        return 0;
    }

    /**
     * Check Laravel filesystem configuration
     */
    private function checkFilesystemConfiguration(): void
    {
        $this->info('1.5. Laravel Filesystem Configuration Check:');

        try {
            // Get the local disk configuration
            $localDiskConfig = config('filesystems.disks.local');
            $this->info('   - Local disk config:');
            $this->info('     - Driver: ' . ($localDiskConfig['driver'] ?? 'not set'));
            $this->info('     - Root: ' . ($localDiskConfig['root'] ?? 'not set'));

            // Check what Laravel thinks the root path is
            $actualRoot = Storage::disk('decta')->path('');
            $this->info("   - Storage facade root path: {$actualRoot}");

            // Compare with expected path
            $expectedRoot = storage_path('app');
            $this->info("   - Expected root path: {$expectedRoot}");

            if ($actualRoot === $expectedRoot) {
                $this->info('   ✅ Root paths match');
            } else {
                $this->error('   ❌ Root paths do NOT match!');
                $this->error("     Expected: {$expectedRoot}");
                $this->error("     Actual: {$actualRoot}");
            }

            // Test a simple path resolution
            $testPath = 'test-path-resolution.txt';
            $resolvedPath = Storage::disk('decta')->path($testPath);
            $expectedPath = storage_path('app/' . $testPath);

            $this->info("   - Test path resolution:");
            $this->info("     - Input: {$testPath}");
            $this->info("     - Resolved: {$resolvedPath}");
            $this->info("     - Expected: {$expectedPath}");

            if ($resolvedPath === $expectedPath) {
                $this->info('   ✅ Path resolution works correctly');
            } else {
                $this->error('   ❌ Path resolution is incorrect!');
            }

        } catch (Exception $e) {
            $this->error("   ❌ Filesystem configuration check failed: {$e->getMessage()}");
        }
    }

    /**
     * Test file operations with detailed analysis
     */
    private function testFileOperationsDetailed(): void
    {
        $this->info('4. Detailed File Operations Test:');

        try {
            $testDate = Carbon::yesterday();
            $dateFolder = $testDate->format('Y/m/d');
            $testDir = config('decta.sftp.local_path') . "/{$dateFolder}";
            $testFile = $testDir . '/test_file_detailed.txt';
            $testContent = 'This is a detailed test file created at ' . Carbon::now()->toISOString();

            $this->info("   - Testing file: {$testFile}");

            // Ensure directory exists
            if (!Storage::disk('decta')->exists($testDir)) {
                Storage::disk('decta')->makeDirectory($testDir);
            }

            // Test file creation via Storage
            $this->info('   - Creating file via Storage facade...');
            Storage::disk('decta')->put($testFile, $testContent);

            // Get both paths
            $storagePath = Storage::disk('decta')->path($testFile);
            $expectedPath = storage_path('app/' . $testFile);

            $this->info("   - Storage facade path: {$storagePath}");
            $this->info("   - Expected direct path: {$expectedPath}");

            // Test Storage facade access
            if (Storage::disk('decta')->exists($testFile)) {
                $this->info('   ✅ File accessible via Storage facade');
                $size = Storage::disk('decta')->size($testFile);
                $this->info("   - File size via Storage: {$size} bytes");
            } else {
                $this->error('   ❌ File not accessible via Storage facade');
                return;
            }

            // Test direct path access using Storage's resolved path
            $this->info('   - Testing direct access using Storage\'s resolved path...');
            if (file_exists($storagePath)) {
                $this->info('   ✅ File exists via Storage\'s resolved path');
                $directSize = filesize($storagePath);
                $this->info("   - File size via resolved path: {$directSize} bytes");
            } else {
                $this->error('   ❌ File does NOT exist via Storage\'s resolved path');
            }

            // Test expected path
            $this->info('   - Testing direct access using expected path...');
            if (file_exists($expectedPath)) {
                $this->info('   ✅ File exists via expected path');
                $expectedSize = filesize($expectedPath);
                $this->info("   - File size via expected path: {$expectedSize} bytes");
            } else {
                $this->error('   ❌ File does NOT exist via expected path');
            }

            // List the actual directory contents where Storage thinks the file is
            $storageDir = dirname($storagePath);
            $this->info("   - Listing contents of Storage directory: {$storageDir}");
            if (is_dir($storageDir)) {
                $files = scandir($storageDir);
                $this->info('     Files found: ' . implode(', ', array_filter($files, fn($f) => $f !== '.' && $f !== '..')));
            } else {
                $this->error('     Directory does not exist');
            }

            // List the expected directory contents
            $expectedDir = dirname($expectedPath);
            $this->info("   - Listing contents of expected directory: {$expectedDir}");
            if (is_dir($expectedDir)) {
                $files = scandir($expectedDir);
                $this->info('     Files found: ' . implode(', ', array_filter($files, fn($f) => $f !== '.' && $f !== '..')));
            } else {
                $this->error('     Directory does not exist');
            }

            // Clean up using Storage facade
            Storage::disk('decta')->delete($testFile);
            $this->info('   ✅ Test file cleaned up via Storage facade');

            // Verify cleanup worked on both paths
            if (!file_exists($storagePath) && !file_exists($expectedPath)) {
                $this->info('   ✅ File removed from both paths');
            } else {
                $this->warn('   ⚠️  File cleanup may not have worked on all paths');
            }

        } catch (Exception $e) {
            $this->error("   ❌ Detailed file operations test failed: {$e->getMessage()}");
        }
    }

    /**
     * Check Decta configuration
     */
    private function checkConfiguration(): void
    {
        $this->info('1. Configuration Check:');

        $config = config('decta.sftp');
        if (!$config) {
            $this->error('   ❌ Decta SFTP configuration not found');
            return;
        }

        $this->info('   ✅ Configuration loaded');
        $this->info("   - Host: {$config['host']}");
        $this->info("   - Port: {$config['port']}");
        $this->info("   - Username: {$config['username']}");
        $this->info("   - Local Path: {$config['local_path']}");
        $this->info("   - Private Key: {$config['private_key_path']}");

        // Check if private key exists
        if (file_exists($config['private_key_path'])) {
            $this->info('   ✅ Private key file exists');
            $perms = substr(sprintf('%o', fileperms($config['private_key_path'])), -4);
            $this->info("   - Key permissions: {$perms}");
        } else {
            $this->error("   ❌ Private key file not found: {$config['private_key_path']}");
        }
    }

    /**
     * Check storage setup
     */
    private function checkStorageSetup(): void
    {
        $this->info('2. Storage Setup Check:');

        // Check storage path
        $storagePath = storage_path('app');
        $this->info("   - Storage path: {$storagePath}");

        if (is_dir($storagePath)) {
            $this->info('   ✅ Storage directory exists');
        } else {
            $this->error('   ❌ Storage directory does not exist');
            return;
        }

        if (is_writable($storagePath)) {
            $this->info('   ✅ Storage directory is writable');
        } else {
            $this->error('   ❌ Storage directory is not writable');
        }

        // Check permissions
        $perms = substr(sprintf('%o', fileperms($storagePath)), -4);
        $this->info("   - Storage permissions: {$perms}");

        // Check disk space
        $freeBytes = disk_free_space($storagePath);
        $totalBytes = disk_total_space($storagePath);

        if ($freeBytes !== false && $totalBytes !== false) {
            $freeGB = round($freeBytes / (1024 * 1024 * 1024), 2);
            $totalGB = round($totalBytes / (1024 * 1024 * 1024), 2);
            $this->info("   - Disk space: {$freeGB}GB free / {$totalGB}GB total");
        }
    }

    /**
     * Test directory creation
     */
    private function testDirectoryCreation(): void
    {
        $this->info('3. Directory Creation Test:');

        try {
            $testDate = Carbon::yesterday();
            $dateFolder = $testDate->format('Y/m/d');
            $testDir = config('decta.sftp.local_path') . "/{$dateFolder}";

            $this->info("   - Testing directory: {$testDir}");

            // Test using Storage facade
            if (!Storage::disk('decta')->exists($testDir)) {
                $this->info('   - Creating directory via Storage facade...');
                Storage::disk('decta')->makeDirectory($testDir);
            }

            if (Storage::disk('decta')->exists($testDir)) {
                $this->info('   ✅ Directory accessible via Storage facade');
            } else {
                $this->error('   ❌ Directory not accessible via Storage facade');
            }

            // Test using direct path
            $fullPath = storage_path('app/' . $testDir);
            $this->info("   - Full path: {$fullPath}");

            if (is_dir($fullPath)) {
                $this->info('   ✅ Directory exists via direct path');
            } else {
                $this->error('   ❌ Directory does not exist via direct path');
            }

            if (is_writable($fullPath)) {
                $this->info('   ✅ Directory is writable via direct path');
            } else {
                $this->error('   ❌ Directory is not writable via direct path');
            }

        } catch (Exception $e) {
            $this->error("   ❌ Directory creation test failed: {$e->getMessage()}");
        }
    }

    /**
     * Test a specific path
     */
    private function testSpecificPath(string $path): void
    {
        $this->info("5. Specific Path Test: {$path}");

        try {
            // Test via Storage facade
            if (Storage::disk('decta')->exists($path)) {
                $this->info('   ✅ Path exists via Storage facade');

                $size = Storage::disk('decta')->size($path);
                $this->info("   - Size via Storage: {$size} bytes");

                $lastModified = Storage::disk('decta')->lastModified($path);
                $this->info("   - Last modified: " . Carbon::createFromTimestamp($lastModified)->toISOString());

                // Get Storage's resolved path
                $resolvedPath = Storage::disk('decta')->path($path);
                $this->info("   - Storage resolved path: {$resolvedPath}");
            } else {
                $this->error('   ❌ Path does not exist via Storage facade');
            }

            // Test via direct path
            $fullPath = storage_path('app/' . $path);
            $this->info("   - Expected direct path: {$fullPath}");

            if (file_exists($fullPath)) {
                $this->info('   ✅ Path exists via expected direct access');

                $directSize = filesize($fullPath);
                $this->info("   - Size via direct access: {$directSize} bytes");

                $mtime = filemtime($fullPath);
                $this->info("   - Modified time: " . Carbon::createFromTimestamp($mtime)->toISOString());

                if (is_readable($fullPath)) {
                    $this->info('   ✅ File is readable');
                } else {
                    $this->error('   ❌ File is not readable');
                }
            } else {
                $this->error('   ❌ Path does not exist via expected direct access');
            }

            // If Storage gave us a different path, test that too
            if (Storage::disk('decta')->exists($path)) {
                $resolvedPath = Storage::disk('decta')->path($path);
                if ($resolvedPath !== $fullPath) {
                    $this->info("   - Testing Storage's resolved path: {$resolvedPath}");
                    if (file_exists($resolvedPath)) {
                        $this->info('   ✅ Path exists via Storage\'s resolved path');
                        $resolvedSize = filesize($resolvedPath);
                        $this->info("   - Size via resolved path: {$resolvedSize} bytes");
                    } else {
                        $this->error('   ❌ Path does not exist via Storage\'s resolved path');
                    }
                }
            }

        } catch (Exception $e) {
            $this->error("   ❌ Specific path test failed: {$e->getMessage()}");
        }
    }
}
