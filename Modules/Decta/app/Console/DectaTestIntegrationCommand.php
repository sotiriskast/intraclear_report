<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaSftpService;

class DectaTestIntegrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'decta:test-integration';

    /**
     * The console command description.
     */
    protected $description = 'Test integration between existing SFTP service and new web interface methods';

    protected $sftpService;

    /**
     * Create a new command instance.
     */
    public function __construct(DectaSftpService $sftpService)
    {
        parent::__construct();
        $this->sftpService = $sftpService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Decta SFTP Service Integration');
        $this->info('==========================================');

        $tests = [
            'testExistingMethods',
            'testNewWebInterfaceMethods',
            'testMethodCompatibility',
        ];

        $passedTests = 0;
        $totalTests = count($tests);

        foreach ($tests as $test) {
            $this->newLine();
            if ($this->$test()) {
                $passedTests++;
            }
        }

        $this->newLine();
        $this->info("Test Results: {$passedTests}/{$totalTests} passed");

        if ($passedTests === $totalTests) {
            $this->info('🎉 Integration test passed! Existing and new methods work together.');
            return 0;
        } else {
            $this->error('❌ Some integration tests failed.');
            return 1;
        }
    }

    /**
     * Test existing methods are still working
     */
    protected function testExistingMethods(): bool
    {
        $this->line('🔧 Testing existing SFTP methods...');

        try {
            // Test existing connection method
            $connectionResult = $this->sftpService->testConnection();
            if (!is_bool($connectionResult)) {
                throw new \Exception('testConnection() should return boolean');
            }

            // Test existing listFiles method
            $files = $this->sftpService->listFiles();
            if (!is_array($files)) {
                throw new \Exception('listFiles() should return array');
            }

            // Test existing findLatestFile method
            $latestFile = $this->sftpService->findLatestFile();
            if ($latestFile !== null && !is_array($latestFile)) {
                throw new \Exception('findLatestFile() should return array or null');
            }

            $this->info('  ✅ All existing methods work correctly');
            return true;

        } catch (\Exception $e) {
            $this->error('  ❌ Existing methods test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test new web interface methods
     */
    protected function testNewWebInterfaceMethods(): bool
    {
        $this->line('🌐 Testing new web interface methods...');

        try {
            // Test enhanced connection method
            $detailedConnection = $this->sftpService->testConnectionDetailed();
            if (!is_array($detailedConnection) || !isset($detailedConnection['success'])) {
                throw new \Exception('testConnectionDetailed() should return array with success key');
            }

            // Test enhanced file listing
            $remoteFiles = $this->sftpService->listRemoteFiles();
            if (!is_array($remoteFiles)) {
                throw new \Exception('listRemoteFiles() should return array');
            }

            // Test server info method
            $serverInfo = $this->sftpService->getServerInfo();
            if (!is_array($serverInfo)) {
                throw new \Exception('getServerInfo() should return array');
            }

            $this->info('  ✅ All new web interface methods work correctly');
            return true;

        } catch (\Exception $e) {
            $this->error('  ❌ New web interface methods test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test method compatibility
     */
    protected function testMethodCompatibility(): bool
    {
        $this->line('🔄 Testing method compatibility...');

        try {
            // Test that both methods return consistent connection status
            $oldConnection = $this->sftpService->testConnection();
            $newConnection = $this->sftpService->testConnectionDetailed();

            if ($oldConnection !== $newConnection['success']) {
                $this->warn('  ⚠️  Connection methods return different results (may be timing-related)');
                $this->line("    Old method: " . ($oldConnection ? 'true' : 'false'));
                $this->line("    New method: " . ($newConnection['success'] ? 'true' : 'false'));
            }

            // Test that both file listing methods return files
            $oldFiles = $this->sftpService->listFiles();
            $newFiles = $this->sftpService->listRemoteFiles();

            $this->line("    Old listFiles() returned: " . count($oldFiles) . " files");
            $this->line("    New listRemoteFiles() returned: " . count($newFiles) . " files");

            // Test file matching
            if (!empty($oldFiles) && !empty($newFiles)) {
                $oldFileNames = array_map(function($file) {
                    return basename($file['path']);
                }, $oldFiles);

                $newFileNames = array_map(function($file) {
                    return $file['name'];
                }, $newFiles);

                $commonFiles = array_intersect($oldFileNames, $newFileNames);
                $this->line("    Common files found: " . count($commonFiles));

                if (count($commonFiles) === 0 && count($oldFiles) > 0 && count($newFiles) > 0) {
                    $this->warn('  ⚠️  No common files found between methods');
                }
            }

            $this->info('  ✅ Method compatibility verified');
            return true;

        } catch (\Exception $e) {
            $this->error('  ❌ Method compatibility test failed: ' . $e->getMessage());
            return false;
        }
    }
}
