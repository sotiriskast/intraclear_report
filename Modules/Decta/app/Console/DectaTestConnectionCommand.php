<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaSftpService;
use Illuminate\Support\Facades\Log;
use Exception;

class DectaTestConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:test-connection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the connection to Decta SFTP server';

    /**
     * @var DectaSftpService
     */
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
        $this->info('Testing connection to Decta SFTP server...');

        // Get SFTP configuration
        $config = config('decta.sftp');

        if (!$config) {
            $this->error('Decta SFTP configuration not found. Make sure the config is published and loaded correctly.');
            return 1;
        }

        // Display connection details
        $this->info("Connection details:");
        $this->info("- Host: {$config['host']}");
        $this->info("- Port: {$config['port']}");
        $this->info("- Username: {$config['username']}");
        $this->info("- Private Key Path: {$config['private_key_path']}");
        $this->info("- Remote Path: {$config['remote_path']}");
        $this->info("- Identities Only: " . ($config['identities_only'] ? 'Yes' : 'No'));

        // Check if private key file exists
        if (!file_exists($config['private_key_path'])) {
            $this->error("Private key file not found at: {$config['private_key_path']}");
            $this->line('Make sure the key file exists and is readable.');
            $this->line('You can copy your key with: cp ~/Downloads/decta_rsa ' . $config['private_key_path']);
            return 1;
        }

        // Check permissions on private key file
        $permissions = substr(sprintf('%o', fileperms($config['private_key_path'])), -4);
        if ($permissions != '0600') {
            $this->warn("Private key file has permissions {$permissions}, but should be 0600 for security.");
            $this->line('Consider fixing permissions with: chmod 600 ' . $config['private_key_path']);
        }

        // Try to connect using the service
        try {
            $this->info("Attempting to connect...");

            // Test basic connection
            if (!$this->sftpService->testConnection()) {
                throw new Exception("Connection test failed");
            }

            $this->info("Connection successful! Listing files...");

            // List files to verify functionality
            $files = $this->sftpService->listFiles();

            if (empty($files)) {
                $this->info("No files found in the remote directory (or no files match the configured extensions).");
            } else {
                $this->info("Found " . count($files) . " files in the remote directory:");

                $table = [];
                foreach (array_slice($files, 0, 10) as $file) { // Show only first 10 files
                    $table[] = [
                        'Path' => $file['path'],
                        'Size' => $this->formatBytes($file['fileSize']),
                        'Type' => $file['type'] ?? 'file'
                    ];
                }

                $this->table(['Path', 'Size', 'Type'], $table);

                if (count($files) > 10) {
                    $this->info("... and " . (count($files) - 10) . " more files.");
                }
            }

            $this->info("Connection test completed successfully!");
            return 0;

        } catch (Exception $e) {
            $this->error("Connection failed: " . $e->getMessage());

            // Provide more detailed error information in verbose mode
            if ($this->option('verbose')) {
                $this->line("\nDetailed error information:");
                $this->line($e->getTraceAsString());
            }

            // Suggest common fixes
            $this->line("\nPossible solutions:");
            $this->line("1. Make sure you copied your private key: cp ~/Downloads/decta_rsa " . storage_path('app/decta/decta_rsa'));
            $this->line("2. Set correct permissions: chmod 600 " . $config['private_key_path']);
            $this->line("3. Check if the server is reachable: ping {$config['host']}");
            $this->line("4. Verify your environment variables in .env file");
            $this->line("5. Test manually: sftp -o IdentitiesOnly=yes -i {$config['private_key_path']} -P {$config['port']} {$config['username']}@{$config['host']}");

            if (!$this->option('verbose')) {
                $this->line("\nRun with --verbose option for more detailed error information.");
            }

            Log::error('SFTP connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }

    /**
     * Format bytes to a human-readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
