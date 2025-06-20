<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaSmsService;
use Illuminate\Support\Facades\Log;
use Exception;

class VisaSmsDownloadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:download-sms-reports
                            {--date= : Specific date to download (YYYY-MM-DD)}
                            {--days-back=7 : Number of days back to check for files}
                            {--process-immediately : Process files immediately after download}
                            {--force : Force download even if files exist}
                            {--dry-run : Show what would be downloaded without downloading}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download Visa SMS transaction detail reports and update interchange field';

    /**
     * Visa SMS Service
     */
    protected VisaSmsService $visaSmsService;

    /**
     * Create a new command instance.
     */
    public function __construct(VisaSmsService $visaSmsService)
    {
        parent::__construct();
        $this->visaSmsService = $visaSmsService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting Visa SMS Reports Download Process');
        $this->line('==========================================');

        try {
            // Prepare options from command arguments
            $options = [
                'date' => $this->option('date'),
                'days_back' => (int) $this->option('days-back'),
                'process_immediately' => $this->option('process-immediately'),
                'force' => $this->option('force'),
                'dry_run' => $this->option('dry-run')
            ];

            // Filter out null values
            $options = array_filter($options, function ($value) {
                return $value !== null && $value !== false;
            });

            $this->info("Checking for files from the last " . ($options['days_back'] ?? 7) . " days");
            if ($options['dry_run'] ?? false) {
                $this->info('🔍 DRY RUN MODE - No actual downloads will be performed');
            }

            // Use the service to download files
            $results = $this->visaSmsService->downloadFiles($options);

            // Display progress during download
            $this->displayProgress($results);

            // Display summary
            $this->displaySummary($results);

            // Display detailed results table if there were any files processed
            if (!empty($results['details'])) {
                $this->displayDetailsTable($results['details']);
            }

            return $results['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("💥 Fatal error: " . $e->getMessage());
            Log::error('Visa SMS download command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Display progress during download
     */
    protected function displayProgress(array $results): void
    {
        foreach ($results['details'] as $detail) {
            $this->line("📅 Checking: {$detail['filename']}");

            if (!$detail['found']) {
                $this->line("   ❌ File not found on SFTP server");
                continue;
            }

            $this->line("   ✅ File found on SFTP server");

            if ($detail['skipped']) {
                $this->line("   ⏭️  File already downloaded");
                continue;
            }

            if (isset($detail['error'])) {
                $this->line("   ❌ Download failed: " . $detail['error']);
                continue;
            }

            if ($detail['downloaded']) {
                $this->line("   ✅ Downloaded successfully");

                // Show processing result if it was processed immediately
                if (isset($detail['process_result'])) {
                    $processResult = $detail['process_result'];
                    if ($processResult['success']) {
                        $updated = $processResult['updated_count'] ?? 0;
                        $notFound = $processResult['not_found_count'] ?? 0;
                        $errors = $processResult['error_count'] ?? 0;
                        $this->line("   🔄 Processed: {$updated} updated, {$notFound} not found, {$errors} errors");
                    } else {
                        $this->line("   ❌ Processing failed: " . ($processResult['error'] ?? 'Unknown error'));
                    }
                }
            } else {
                $this->line("   🔍 [DRY RUN] Would download: {$detail['filename']}");
            }
        }
    }

    /**
     * Display summary
     */
    protected function displaySummary(array $results): void
    {
        $this->line('');
        $this->info('📊 Download Summary');
        $this->line('==================');
        $this->line("Files found on SFTP: {$results['files_found']}");
        $this->line("Files downloaded: {$results['files_downloaded']}");
        $this->line("Files skipped: {$results['files_skipped']}");

        if ($results['files_processed'] > 0) {
            $this->line("Files processed: {$results['files_processed']}");
        }

        if ($results['errors'] > 0) {
            $this->line("Errors encountered: {$results['errors']}");
        }
    }

    /**
     * Display detailed results table
     */
    protected function displayDetailsTable(array $details): void
    {
        $this->line('');
        $this->info('📋 Detailed Results');
        $this->line('===================');

        $tableData = [];
        foreach ($details as $detail) {
            $status = '❌ Failed';

            if ($detail['downloaded']) {
                $status = '✅ Downloaded';
            } elseif ($detail['skipped']) {
                $status = '⏭️ Skipped';
            } elseif (!$detail['found']) {
                $status = '❌ Not Found';
            } elseif (isset($detail['message']) && str_contains($detail['message'], 'DRY RUN')) {
                $status = '🔍 Dry Run';
            }

            $message = $detail['message'] ?? '';
            if (isset($detail['error'])) {
                $message = $detail['error'];
            }

            $tableData[] = [
                $detail['filename'],
                $detail['date'],
                $status,
                $message
            ];
        }

        $this->table(
            ['Filename', 'Date', 'Status', 'Message'],
            $tableData
        );
    }
}
