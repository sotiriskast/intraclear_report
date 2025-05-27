<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Models\DectaFile;
use Modules\Decta\Models\DectaTransaction;
use Modules\Decta\Services\DectaTransactionService;
use Modules\Decta\Repositories\DectaFileRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DectaRecoveryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'decta:recovery
                            {--stuck-files : Reset files stuck in processing status}
                            {--resume-file= : Resume processing for a specific file ID}
                            {--verify-file= : Verify completeness of a processed file}
                            {--cleanup : Clean up inconsistent states}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Recover from interrupted Decta file processing';

    protected DectaTransactionService $transactionService;
    protected DectaFileRepository $fileRepository;

    public function __construct(
        DectaTransactionService $transactionService,
        DectaFileRepository $fileRepository
    ) {
        parent::__construct();
        $this->transactionService = $transactionService;
        $this->fileRepository = $fileRepository;
    }

    public function handle()
    {
        $this->info('🔧 Decta Recovery Tool');
        $this->line(str_repeat('=', 50));

        $stuckFiles = $this->option('stuck-files');
        $resumeFile = $this->option('resume-file');
        $verifyFile = $this->option('verify-file');
        $cleanup = $this->option('cleanup');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try {
            if ($stuckFiles) {
                return $this->handleStuckFiles($dryRun);
            }

            if ($resumeFile) {
                return $this->resumeFileProcessing((int)$resumeFile, $dryRun);
            }

            if ($verifyFile) {
                return $this->verifyFileCompleteness((int)$verifyFile);
            }

            if ($cleanup) {
                return $this->cleanupInconsistentStates($dryRun);
            }

            // If no specific option, show overview
            $this->showRecoveryOverview();
            return 0;

        } catch (Exception $e) {
            $this->error("Recovery failed: {$e->getMessage()}");
            Log::error('Decta recovery failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Handle files stuck in processing status
     */
    private function handleStuckFiles(bool $dryRun): int
    {
        $this->info('🔍 Looking for stuck files...');

        // Find files stuck in processing for more than 2 hours
        $cutoffTime = Carbon::now()->subHours(2);
        $stuckFiles = DectaFile::where('status', DectaFile::STATUS_PROCESSING)
            ->where('updated_at', '<', $cutoffTime)
            ->get();

        if ($stuckFiles->isEmpty()) {
            $this->info('✅ No stuck files found.');
            return 0;
        }

        $this->warn("Found {$stuckFiles->count()} stuck file(s):");

        $headers = ['ID', 'Filename', 'Status', 'Updated At', 'Transactions', 'Action'];
        $tableData = [];

        foreach ($stuckFiles as $file) {
            $transactionCount = $file->dectaTransactions()->count();
            $action = $this->determineStuckFileAction($file, $transactionCount);

            $tableData[] = [
                $file->id,
                $file->filename,
                $file->status,
                $file->updated_at->diffForHumans(),
                $transactionCount,
                $action
            ];
        }

        $this->table($headers, $tableData);

        if ($dryRun) {
            $this->info('🔍 Dry run complete - no changes made.');
            return 0;
        }

        if (!$this->confirm('Proceed with recovery actions?')) {
            $this->info('Recovery cancelled.');
            return 0;
        }

        $recovered = 0;
        foreach ($stuckFiles as $file) {
            try {
                $transactionCount = $file->dectaTransactions()->count();
                $action = $this->determineStuckFileAction($file, $transactionCount);

                switch ($action) {
                    case 'Reset to pending':
                        $file->update(['status' => DectaFile::STATUS_PENDING]);
                        $this->line("✅ Reset {$file->filename} to pending status");
                        break;

                    case 'Resume processing':
                        $this->resumeFileProcessing($file->id, false);
                        break;

                    case 'Mark as failed':
                        $file->markAsFailed('Processing interrupted and cannot be resumed');
                        $this->line("❌ Marked {$file->filename} as failed");
                        break;
                }

                $recovered++;
            } catch (Exception $e) {
                $this->error("Failed to recover {$file->filename}: {$e->getMessage()}");
            }
        }

        $this->info("✅ Recovered {$recovered} out of {$stuckFiles->count()} stuck files.");
        return 0;
    }

    /**
     * Resume processing for a specific file
     */
    private function resumeFileProcessing(int $fileId, bool $dryRun): int
    {
        $this->info("🔄 Resuming processing for file ID: {$fileId}");

        $file = DectaFile::find($fileId);
        if (!$file) {
            $this->error("File with ID {$fileId} not found.");
            return 1;
        }

        $this->line("File: {$file->filename}");
        $this->line("Status: {$file->status}");
        $this->line("Created: {$file->created_at}");

        // Check file integrity
        $integrity = $this->fileRepository->validateFileIntegrity($file);
        if (!$integrity['exists_in_storage']) {
            $this->error('❌ File no longer exists in storage. Cannot resume.');
            return 1;
        }

        // Get current transaction count
        $existingCount = $file->dectaTransactions()->count();
        $this->line("Existing transactions: {$existingCount}");

        // Count total rows in CSV
        $content = $this->fileRepository->getFileContent($file);
        if (!$content) {
            $this->error('❌ Cannot read file content.');
            return 1;
        }

        $totalRows = $this->countCsvRows($content);
        $this->line("Total CSV rows: {$totalRows}");

        if ($existingCount >= $totalRows) {
            $this->info('✅ File appears to be completely processed.');
            if (!$dryRun) {
                $file->markAsProcessed();
                $this->info('✅ Marked file as processed.');
            }
            return 0;
        }

        $remaining = $totalRows - $existingCount;
        $this->warn("⚠️  {$remaining} rows still need to be processed.");

        if ($dryRun) {
            $this->info('🔍 Dry run complete - would resume processing the remaining rows.');
            return 0;
        }

        if (!$this->confirm('Resume processing the remaining rows?')) {
            $this->info('Resume cancelled.');
            return 0;
        }

        // Resume processing
        return $this->resumeProcessingFromOffset($file, $existingCount);
    }

    /**
     * Resume processing from a specific offset
     */
    private function resumeProcessingFromOffset(DectaFile $file, int $offset): int
    {
        try {
            $this->info("🔄 Resuming processing from row {$offset}...");

            $file->markAsProcessing();

            // Get file content
            $content = $this->fileRepository->getFileContent($file);
            if (!$content) {
                throw new Exception('Cannot read file content');
            }

            // Process remaining rows
            $result = $this->processRemainingRows($file, $content, $offset);

            if ($result['processed'] > 0) {
                $file->markAsProcessed();
                $this->info("✅ Successfully processed {$result['processed']} additional rows.");
                $this->info("✅ File processing completed.");

                // Optionally trigger matching
                if ($this->confirm('Run transaction matching now?')) {
                    $this->call('decta:match-transactions', ['--file-id' => $file->id]);
                }
            } else {
                $file->markAsFailed('No additional rows could be processed');
                $this->error('❌ No additional rows were processed.');
            }

            return 0;

        } catch (Exception $e) {
            $file->markAsFailed("Resume failed: {$e->getMessage()}");
            $this->error("❌ Resume failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Process remaining rows from a specific offset
     */
    private function processRemainingRows(DectaFile $file, string $content, int $offset): array
    {
        $lines = $this->parseContentToLines($content);
        $headers = $this->parseCsvLine(array_shift($lines)); // Remove header
        $headers = $this->normalizeHeaders($headers);

        $results = ['processed' => 0, 'failed' => 0, 'errors' => []];

        // Skip already processed rows
        $remainingLines = array_slice($lines, $offset);

        $this->info("Processing {count($remainingLines)} remaining rows...");

        $progressBar = $this->output->createProgressBar(count($remainingLines));
        $progressBar->start();

        foreach ($remainingLines as $index => $line) {
            try {
                if (empty(trim($line))) {
                    continue;
                }

                $data = $this->parseCsvLine($line);

                // Handle column count mismatch
                if (count($data) !== count($headers)) {
                    if (count($data) < count($headers)) {
                        $data = array_pad($data, count($headers), '');
                    } else {
                        $data = array_slice($data, 0, count($headers));
                    }
                }

                $rowData = array_combine($headers, $data);
                if ($rowData === false) {
                    throw new Exception("Failed to combine headers with data");
                }

                // Check if this transaction already exists (by payment_id)
                if (isset($rowData['PAYMENT_ID']) && !empty($rowData['PAYMENT_ID'])) {
                    $exists = DectaTransaction::where('decta_file_id', $file->id)
                        ->where('payment_id', $rowData['PAYMENT_ID'])
                        ->exists();

                    if ($exists) {
                        continue; // Skip duplicate
                    }
                }

                // Store the transaction
                $this->storeTransaction($file, $rowData);
                $results['processed']++;

            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Row " . ($offset + $index + 2) . ": " . $e->getMessage();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return $results;
    }

    /**
     * Verify file completeness
     */
    private function verifyFileCompleteness(int $fileId): int
    {
        $this->info("🔍 Verifying completeness of file ID: {$fileId}");

        $file = DectaFile::find($fileId);
        if (!$file) {
            $this->error("File with ID {$fileId} not found.");
            return 1;
        }

        // Check file integrity
        $integrity = $this->fileRepository->validateFileIntegrity($file);

        $this->line("File: {$file->filename}");
        $this->line("Status: {$file->status}");
        $this->line("Storage exists: " . ($integrity['exists_in_storage'] ? '✅' : '❌'));
        $this->line("Size matches: " . ($integrity['size_matches'] ? '✅' : '❌'));
        $this->line("Is readable: " . ($integrity['is_readable'] ? '✅' : '❌'));

        if (!empty($integrity['issues'])) {
            $this->warn("Issues found:");
            foreach ($integrity['issues'] as $issue) {
                $this->line("  ⚠️  {$issue}");
            }
        }

        if (!$integrity['exists_in_storage']) {
            return 1;
        }

        // Count rows
        $content = $this->fileRepository->getFileContent($file);
        $csvRows = $this->countCsvRows($content);
        $dbTransactions = $file->dectaTransactions()->count();

        $this->line("CSV rows (excluding header): {$csvRows}");
        $this->line("Database transactions: {$dbTransactions}");

        if ($csvRows === $dbTransactions) {
            $this->info("✅ File is complete - all rows processed.");
        } else {
            $missing = $csvRows - $dbTransactions;
            $this->warn("⚠️  {$missing} rows are missing from the database.");

            if ($this->confirm('Resume processing for missing rows?')) {
                return $this->resumeFileProcessing($fileId, false);
            }
        }

        return 0;
    }

    /**
     * Clean up inconsistent states
     */
    private function cleanupInconsistentStates(bool $dryRun): int
    {
        $this->info('🧹 Cleaning up inconsistent states...');

        $issues = [];

        // Find files marked as processed but with no transactions
        $emptyProcessedFiles = DectaFile::where('status', DectaFile::STATUS_PROCESSED)
            ->whereDoesntHave('dectaTransactions')
            ->get();

        if ($emptyProcessedFiles->isNotEmpty()) {
            $issues[] = [
                'type' => 'Empty processed files',
                'count' => $emptyProcessedFiles->count(),
                'action' => 'Reset to pending',
                'files' => $emptyProcessedFiles
            ];
        }

        // Find transactions without files
        $orphanTransactions = DectaTransaction::whereDoesntHave('dectaFile')->count();
        if ($orphanTransactions > 0) {
            $issues[] = [
                'type' => 'Orphan transactions',
                'count' => $orphanTransactions,
                'action' => 'Delete orphan records',
                'files' => []
            ];
        }

        if (empty($issues)) {
            $this->info('✅ No inconsistent states found.');
            return 0;
        }

        foreach ($issues as $issue) {
            $this->warn("Found {$issue['count']} {$issue['type']} - Action: {$issue['action']}");
        }

        if ($dryRun) {
            $this->info('🔍 Dry run complete - no cleanup performed.');
            return 0;
        }

        if (!$this->confirm('Proceed with cleanup?')) {
            $this->info('Cleanup cancelled.');
            return 0;
        }

        $cleaned = 0;
        foreach ($issues as $issue) {
            try {
                switch ($issue['type']) {
                    case 'Empty processed files':
                        foreach ($issue['files'] as $file) {
                            $file->update(['status' => DectaFile::STATUS_PENDING]);
                        }
                        $cleaned += $issue['count'];
                        break;

                    case 'Orphan transactions':
                        $deleted = DectaTransaction::whereDoesntHave('dectaFile')->delete();
                        $cleaned += $deleted;
                        break;
                }
            } catch (Exception $e) {
                $this->error("Failed to clean up {$issue['type']}: {$e->getMessage()}");
            }
        }

        $this->info("✅ Cleaned up {$cleaned} inconsistent records.");
        return 0;
    }

    /**
     * Show recovery overview
     */
    private function showRecoveryOverview(): void
    {
        $this->info('📊 Decta Recovery Overview');
        $this->newLine();

        // Stuck files
        $stuckFiles = DectaFile::where('status', DectaFile::STATUS_PROCESSING)
            ->where('updated_at', '<', Carbon::now()->subHours(2))
            ->count();

        // Failed files
        $failedFiles = DectaFile::where('status', DectaFile::STATUS_FAILED)->count();

        // Incomplete files (processed but missing transactions)
        $incompleteFiles = DectaFile::where('status', DectaFile::STATUS_PROCESSED)
            ->whereDoesntHave('dectaTransactions')
            ->count();

        $this->table(['Issue Type', 'Count', 'Command to Fix'], [
            ['Stuck files (>2h processing)', $stuckFiles, 'decta:recovery --stuck-files'],
            ['Failed files', $failedFiles, 'Check logs and retry specific files'],
            ['Incomplete processed files', $incompleteFiles, 'decta:recovery --cleanup'],
        ]);

        if ($stuckFiles > 0 || $failedFiles > 0 || $incompleteFiles > 0) {
            $this->newLine();
            $this->warn('⚠️  Issues detected. Use the suggested commands to fix them.');
        } else {
            $this->info('✅ No issues detected.');
        }

        $this->newLine();
        $this->line('Available recovery options:');
        $this->line('  --stuck-files         Reset files stuck in processing');
        $this->line('  --resume-file=ID      Resume processing for specific file');
        $this->line('  --verify-file=ID      Verify file completeness');
        $this->line('  --cleanup             Clean up inconsistent states');
        $this->line('  --dry-run             Preview changes without applying them');
    }

    // Helper methods
    private function determineStuckFileAction(DectaFile $file, int $transactionCount): string
    {
        if ($transactionCount === 0) {
            return 'Reset to pending';
        }

        // Check if file content is available
        if (!$this->fileRepository->fileExistsInStorage($file)) {
            return 'Mark as failed';
        }

        return 'Resume processing';
    }

    private function countCsvRows(string $content): int
    {
        $lines = $this->parseContentToLines($content);
        return max(0, count($lines) - 1); // Subtract header row
    }

    private function parseContentToLines(string $content): array
    {
        $content = $this->removeBOM($content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        return array_filter($lines, fn($line) => !empty(trim($line)));
    }

    private function removeBOM(string $content): string
    {
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        return $content;
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(function($header) {
            $header = trim($header);
            $header = str_replace(["\xEF\xBB\xBF", "\r", "\n"], '', $header);
            return strtoupper($header);
        }, $headers);
    }

    private function parseCsvLine(string $line): array
    {
        if (strpos($line, ';') !== false) {
            return str_getcsv($line, ';');
        }
        return str_getcsv($line, ',');
    }

    private function storeTransaction(DectaFile $file, array $rowData): DectaTransaction
    {
        // Use the same logic as DectaTransactionService
        return $this->transactionService->storeTransaction($file, $rowData);
    }
}
