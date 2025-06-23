<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaIssuesService;
use Illuminate\Support\Facades\Log;
use Exception;

class VisaIssuesProcessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:process-issues-reports
                            {--file-id= : Process specific file by ID}
                            {--filename= : Process specific file by filename}
                            {--status=pending : Process files with specific status (pending,failed)}
                            {--reprocess : Reprocess already processed files}
                            {--dry-run : Show what would be processed without processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process Visa Issues transaction files to update interchange field in existing transactions';

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
        $this->info('🔄 Visa Issues Reports Processing');
        $this->line('=================================');

        try {
            // Prepare options from command arguments
            $options = [
                'file_id' => $this->option('file-id'),
                'filename' => $this->option('filename'),
                'status' => $this->option('status'),
                'reprocess' => $this->option('reprocess'),
                'dry_run' => $this->option('dry-run')
            ];

            // Filter out null values
            $options = array_filter($options, function ($value) {
                return $value !== null && $value !== false;
            });

            if ($options['dry_run'] ?? false) {
                $this->info('🔍 DRY RUN MODE - No actual processing will be performed');
                $this->line('');
            }

            // Use the service to process files
            $results = $this->visaIssuesService->processFiles($options);

            if (empty($results['details'])) {
                $this->warn('No Visa Issues files found to process.');
                $this->line('');
                $this->info('💡 Available commands:');
                $this->line('• List available files: php artisan visa:download-issues-reports --list');
                $this->line('• Download file: php artisan visa:download-issues-reports FILENAME');
                $this->line('• Check status: php artisan visa:issues-status');
                return Command::SUCCESS;
            }

            $this->info("Found " . count($results['details']) . " file(s) to process");
            $this->line('');

            // Display progress during processing
            $this->displayProgress($results);

            // Display summary
            $this->displaySummary($results);

            // Display detailed results table
            $this->displayDetailsTable($results['details']);

            return $results['files_failed'] > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("💥 Fatal error: " . $e->getMessage());
            Log::error('Visa Issues processing command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Display progress during processing
     */
    protected function displayProgress(array $results): void
    {
        foreach ($results['details'] as $detail) {
            $this->line("📁 Processing: {$detail['filename']}");

            if (isset($detail['dry_run']) && $detail['dry_run']) {
                $updateCount = $detail['update_count'] ?? 0;
                $totalRows = $detail['total_rows'] ?? 0;
                $this->line("   🔍 [DRY RUN] Would update {$updateCount} transactions");
                $this->line("   📊 File contains {$totalRows} rows");
            } elseif ($detail['success']) {
                $updated = $detail['updated_count'] ?? 0;
                $notFound = $detail['not_found_count'] ?? 0;
                $errors = $detail['error_count'] ?? 0;
                $this->line("   ✅ Updated {$updated} transactions");
                if ($notFound > 0) {
                    $this->line("   ℹ️  {$notFound} transactions not found in database");
                }
                if ($errors > 0) {
                    $this->line("   ⚠️  {$errors} errors encountered");
                }
            } else {
                $error = $detail['error'] ?? 'Unknown error';
                $this->line("   ❌ Processing failed: {$error}");
            }
        }
    }

    /**
     * Display summary
     */
    protected function displaySummary(array $results): void
    {
        $this->line('');
        $this->info('📊 Processing Summary');
        $this->line('====================');

        if (isset($results['details'][0]['dry_run'])) {
            $this->line("Files analyzed: " . count($results['details']));
            $this->line("Mode: DRY RUN (no actual changes made)");
        } else {
            $this->line("Files processed: {$results['files_processed']}");
            $this->line("Files failed: {$results['files_failed']}");
            $this->line("Total transactions updated: {$results['total_transactions_updated']}");
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
            $stats = '';

            if (isset($detail['dry_run']) && $detail['dry_run']) {
                $status = '🔍 Dry Run';
                $updateCount = $detail['update_count'] ?? 0;
                $totalRows = $detail['total_rows'] ?? 0;
                $stats = "Would update {$updateCount}/{$totalRows}";
            } elseif ($detail['success']) {
                $status = '✅ Success';
                $updated = $detail['updated_count'] ?? 0;
                $notFound = $detail['not_found_count'] ?? 0;
                $errors = $detail['error_count'] ?? 0;
                $stats = "Updated: {$updated}, Not found: {$notFound}, Errors: {$errors}";
            } else {
                $stats = $detail['error'] ?? 'Unknown error';
            }

            // Extract date range for display
            $filename = $detail['filename'];
            $shortName = $filename;
            if (preg_match('/(\d{8}-\d{8})/', $filename, $matches)) {
                $shortName = $matches[1];
            }

            $tableData[] = [
                $shortName,
                $detail['file_id'] ?? 'N/A',
                $status,
                $stats
            ];
        }

        $this->table(
            ['Period', 'File ID', 'Status', 'Statistics'],
            $tableData
        );
    }
}
