<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaSmsService;
use Illuminate\Support\Facades\Log;
use Exception;

class VisaSmsProcessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:process-sms-reports
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
    protected $description = 'Process Visa SMS transaction files to update interchange field in existing transactions';

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
        $this->info('🔄 Starting Visa SMS Reports Processing');
        $this->line('=========================================');

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
            $results = $this->visaSmsService->processFiles($options);

            if (empty($results['details'])) {
                $this->warn('No files found to process.');
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
            Log::error('Visa SMS processing command failed', [
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

            $tableData[] = [
                $detail['filename'],
                $detail['file_id'] ?? 'N/A',
                $status,
                $stats
            ];
        }

        $this->table(
            ['Filename', 'File ID', 'Status', 'Statistics'],
            $tableData
        );
    }
}
