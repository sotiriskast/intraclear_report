<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaIssuesService;
use Illuminate\Support\Facades\Log;
use Exception;

class VisaIssuesDownloadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:download-issues-reports
                            {filename? : Specific filename to download}
                            {--list : List available files on SFTP server}
                            {--process-immediately : Process file immediately after download}
                            {--force : Force download even if file exists}
                            {--dry-run : Show what would be downloaded without downloading}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download Visa Issues reports from /in_file/Different issues directory';

    /**
     * Visa Issues Service
     */
    protected VisaIssuesService $visaIssuesService;

    /**
     * Create a new command instance.
     */
    public function __construct(VisaIssuesService $visaIssuesService)
    {
        parent::__construct();
        $this->visaIssuesService = $visaIssuesService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📋 Visa Issues Reports Download');
        $this->line('================================');

        try {
            $filename = $this->argument('filename');
            $listOnly = $this->option('list');
            $isDryRun = $this->option('dry-run');

            // If list option is provided, show available files
            if ($listOnly || !$filename) {
                $this->listAvailableFiles();

                if (!$filename && !$listOnly) {
                    $this->line('');
                    $this->warn('Please specify a filename to download or use --list to see available files.');
                    $this->line('Usage: php artisan visa:download-issues-reports INTCL_visa_sms_tr_det_20250501-20250531.csv');
                    return Command::SUCCESS;
                }

                if ($listOnly) {
                    return Command::SUCCESS;
                }
            }

            // Download specific file
            if ($isDryRun) {
                $this->info('🔍 DRY RUN MODE - No actual download will be performed');
                $this->line('');
            }

            $this->info("📥 Downloading: {$filename}");

            $options = [
                'force' => $this->option('force'),
                'dry_run' => $isDryRun
            ];

            $result = $this->visaIssuesService->downloadFile($filename, $options);

            // Display result
            $this->displayDownloadResult($result);

            // Process immediately if requested
            if ($result['downloaded'] && $this->option('process-immediately') && !$isDryRun) {
                $this->line('');
                $this->info('🔄 Processing immediately...');
                $this->processFile($result['file_record']);
            }

            return $result['success'] || $result['skipped'] ? Command::SUCCESS : Command::FAILURE;

        } catch (Exception $e) {
            $this->error("💥 Fatal error: " . $e->getMessage());
            Log::error('Visa Issues download command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * List available files on SFTP server
     */
    protected function listAvailableFiles(): void
    {
        $this->info('📂 Available Files on SFTP Server');
        $this->line('==================================');

        try {
            $files = $this->visaIssuesService->listAvailableFiles();

            if (empty($files)) {
                $this->warn('No Visa Issues files found on SFTP server.');
                $this->line('Expected location: /in_file/Different issues');
                $this->line('Expected pattern: INTCL_visa_sms_tr_det_YYYYMMDD-YYYYMMDD.csv');
                return;
            }

            $this->line("Found " . count($files) . " file(s):");
            $this->line('');

            $tableData = [];
            foreach ($files as $file) {
                $status = $file['is_downloaded'] ? '✅ Downloaded' : '📥 Available';
                if ($file['is_downloaded'] && $file['local_status']) {
                    $status .= " ({$file['local_status']})";
                }

                $dateRange = $file['date_range']['period'] ?? 'Unknown';

                $tableData[] = [
                    $file['filename'],
                    $file['size_human'],
                    $file['modified_human'],
                    $dateRange,
                    $status
                ];
            }

            $this->table(
                ['Filename', 'Size', 'Modified', 'Period', 'Status'],
                $tableData
            );

            $this->line('');
            $this->info('💡 Usage Examples:');
            $this->line('Download: php artisan visa:download-issues-reports ' . $files[0]['filename']);
            $this->line('Download and process: php artisan visa:download-issues-reports ' . $files[0]['filename'] . ' --process-immediately');

        } catch (Exception $e) {
            $this->error("Failed to list files: " . $e->getMessage());
        }
    }

    /**
     * Display download result
     */
    protected function displayDownloadResult(array $result): void
    {
        if ($result['success']) {
            $this->line("   ✅ Downloaded successfully");
            if (isset($result['file_record'])) {
                $this->line("   📁 File ID: {$result['file_record']->id}");
                $this->line("   📂 Local path: {$result['local_path']}");
            }
        } elseif ($result['skipped']) {
            $this->line("   ⏭️  File already downloaded");
            if (isset($result['file_record'])) {
                $this->line("   📁 File ID: {$result['file_record']->id}");
                $this->line("   📊 Status: {$result['file_record']->status}");
            }
        } else {
            $this->line("   ❌ Download failed: " . $result['message']);
        }
    }

    /**
     * Process file immediately
     */
    protected function processFile($fileRecord): void
    {
        try {
            $result = $this->visaIssuesService->processFile($fileRecord);

            if ($result['success']) {
                $updated = $result['updated_count'] ?? 0;
                $notFound = $result['not_found_count'] ?? 0;
                $errors = $result['error_count'] ?? 0;

                $this->line("   ✅ Processing completed");
                $this->line("   📊 Updated: {$updated}, Not found: {$notFound}, Errors: {$errors}");
            } else {
                $this->line("   ❌ Processing failed: " . ($result['error'] ?? 'Unknown error'));
            }

        } catch (Exception $e) {
            $this->line("   💥 Processing error: " . $e->getMessage());
        }
    }
}
