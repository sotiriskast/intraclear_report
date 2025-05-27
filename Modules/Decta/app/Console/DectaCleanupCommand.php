<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Modules\Decta\Models\DectaFile;
use Modules\Decta\Models\DectaTransaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DectaCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:cleanup
                            {--days-old=90 : Remove records older than specified days}
                            {--remove-files : Also remove physical files from storage}
                            {--remove-unmatched : Remove old unmatched transactions}
                            {--remove-processed : Remove old successfully processed files}
                            {--reset-stuck : Reset stuck processing files}
                            {--dry-run : Show what would be cleaned without actually doing it}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old Decta files and transactions';

    /**
     * @var DectaFileRepository
     */
    protected $fileRepository;

    /**
     * @var DectaTransactionRepository
     */
    protected $transactionRepository;

    /**
     * Create a new command instance.
     */
    public function __construct(
        DectaFileRepository $fileRepository,
        DectaTransactionRepository $transactionRepository
    ) {
        parent::__construct();
        $this->fileRepository = $fileRepository;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Decta cleanup process...');

        try {
            $daysOld = (int) $this->option('days-old');
            $removeFiles = $this->option('remove-files');
            $removeUnmatched = $this->option('remove-unmatched');
            $removeProcessed = $this->option('remove-processed');
            $resetStuck = $this->option('reset-stuck');
            $dryRun = $this->option('dry-run');
            $force = $this->option('force');

            if ($dryRun) {
                $this->warn('DRY RUN MODE - No changes will be made');
            }

            $cutoffDate = Carbon::now()->subDays($daysOld);
            $this->info("Cleanup cutoff date: {$cutoffDate->toDateString()}");

            // Show current statistics
            $this->displayCurrentStats();

            $cleanupResults = [
                'files_removed' => 0,
                'transactions_removed' => 0,
                'physical_files_removed' => 0,
                'stuck_files_reset' => 0,
                'errors' => [],
            ];

            // Reset stuck files first
            if ($resetStuck) {
                $cleanupResults['stuck_files_reset'] = $this->resetStuckFiles($dryRun);
            }

            // Clean up old unmatched transactions
            if ($removeUnmatched) {
                $cleanupResults['transactions_removed'] += $this->cleanupUnmatchedTransactions($daysOld, $dryRun, $force);
            }

            // Clean up old processed files
            if ($removeProcessed) {
                $result = $this->cleanupProcessedFiles($cutoffDate, $removeFiles, $dryRun, $force);
                $cleanupResults['files_removed'] += $result['files_removed'];
                $cleanupResults['physical_files_removed'] += $result['physical_files_removed'];
                $cleanupResults['errors'] = array_merge($cleanupResults['errors'], $result['errors']);
            }

            // Clean up orphaned transactions (transactions without files)
            $cleanupResults['transactions_removed'] += $this->cleanupOrphanedTransactions($dryRun);

            // Display results
            $this->displayCleanupResults($cleanupResults);

            // Log cleanup activity
            if (!$dryRun) {
                Log::info('Decta cleanup completed', [
                    'results' => $cleanupResults,
                    'cutoff_date' => $cutoffDate->toDateString(),
                    'options' => [
                        'days_old' => $daysOld,
                        'remove_files' => $removeFiles,
                        'remove_unmatched' => $removeUnmatched,
                        'remove_processed' => $removeProcessed,
                        'reset_stuck' => $resetStuck,
                    ],
                ]);
            }

            return empty($cleanupResults['errors']) ? 0 : 1;

        } catch (Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");
            Log::error('Decta cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Display current statistics
     */
    private function displayCurrentStats(): void
    {
        $fileStats = $this->fileRepository->getStatistics(365); // Last year
        $transactionStats = $this->transactionRepository->getStatistics();

        $this->info("\nCurrent Statistics:");
        $this->info(" Files:");
        $this->info("  - Total: {$fileStats['total']}");
        $this->info("  - Processed: {$fileStats['processed']}");
        $this->info("  - Failed: {$fileStats['failed']}");
        $this->info("  - Pending: {$fileStats['pending']}");

        $this->info(" Transactions:");
        $this->info("  - Total: {$transactionStats['total']}");
        $this->info("  - Matched: {$transactionStats['matched']}");
        $this->info("  - Unmatched: {$transactionStats['unmatched']}");
        $this->info("  - Failed: {$transactionStats['failed']}");

        // Check for stuck files
        $stuckFiles = DectaFile::where('status', DectaFile::STATUS_PROCESSING)
            ->where('updated_at', '<', Carbon::now()->subHours(2))
            ->count();

        if ($stuckFiles > 0) {
            $this->warn("  - Stuck in processing: {$stuckFiles} files");
        }

        $this->newLine();
    }

    /**
     * Reset stuck processing files
     */
    private function resetStuckFiles(bool $dryRun): int
    {
        $stuckFiles = DectaFile::where('status', DectaFile::STATUS_PROCESSING)
            ->where('updated_at', '<', Carbon::now()->subHours(2))
            ->get();

        if ($stuckFiles->isEmpty()) {
            $this->info('No stuck files found.');
            return 0;
        }

        $this->info("Found {$stuckFiles->count()} stuck files:");
        foreach ($stuckFiles as $file) {
            $stuckHours = Carbon::now()->diffInHours($file->updated_at);
            $this->line("  - {$file->filename} (stuck for {$stuckHours} hours)");
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would reset {$stuckFiles->count()} stuck files");
            return $stuckFiles->count();
        }

        if (!$this->option('force') && !$this->confirm('Reset these stuck files?')) {
            return 0;
        }

        $resetCount = 0;
        foreach ($stuckFiles as $file) {
            try {
                $file->update([
                    'status' => DectaFile::STATUS_PENDING,
                    'error_message' => 'Reset from stuck processing state',
                ]);
                $resetCount++;
            } catch (Exception $e) {
                $this->error("Failed to reset {$file->filename}: {$e->getMessage()}");
            }
        }

        $this->info("Reset {$resetCount} stuck files");
        return $resetCount;
    }

    /**
     * Clean up old unmatched transactions
     */
    private function cleanupUnmatchedTransactions(int $daysOld, bool $dryRun, bool $force): int
    {
        $count = $this->transactionRepository->cleanupOldUnmatched($daysOld);

        if ($count === 0) {
            $this->info('No old unmatched transactions found for cleanup.');
            return 0;
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would remove {$count} old unmatched transactions");
            return $count;
        }

        if (!$force && !$this->confirm("Remove {$count} old unmatched transactions?")) {
            return 0;
        }

        $removed = $this->transactionRepository->cleanupOldUnmatched($daysOld);
        $this->info("Removed {$removed} old unmatched transactions");
        return $removed;
    }

    /**
     * Clean up old processed files
     */
    private function cleanupProcessedFiles(Carbon $cutoffDate, bool $removeFiles, bool $dryRun, bool $force): array
    {
        $result = [
            'files_removed' => 0,
            'physical_files_removed' => 0,
            'errors' => [],
        ];

        $oldFiles = DectaFile::where('status', DectaFile::STATUS_PROCESSED)
            ->where('created_at', '<', $cutoffDate)
            ->with('dectaTransactions')
            ->get();

        if ($oldFiles->isEmpty()) {
            $this->info('No old processed files found for cleanup.');
            return $result;
        }

        $totalTransactions = $oldFiles->sum(function($file) {
            return $file->dectaTransactions->count();
        });

        $this->info("Found {$oldFiles->count()} old processed files with {$totalTransactions} transactions:");
        foreach ($oldFiles->take(5) as $file) {
            $transactionCount = $file->dectaTransactions->count();
            $this->line("  - {$file->filename} ({$transactionCount} transactions)");
        }

        if ($oldFiles->count() > 5) {
            $this->line("  ... and " . ($oldFiles->count() - 5) . " more files");
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would remove {$oldFiles->count()} files and {$totalTransactions} transactions");
            if ($removeFiles) {
                $this->info("DRY RUN: Would also remove physical files from storage");
            }
            return [
                'files_removed' => $oldFiles->count(),
                'physical_files_removed' => $removeFiles ? $oldFiles->count() : 0,
                'errors' => [],
            ];
        }

        if (!$force && !$this->confirm("Remove {$oldFiles->count()} old processed files and their {$totalTransactions} transactions?")) {
            return $result;
        }

        foreach ($oldFiles as $file) {
            try {
                // Remove physical file if requested
                if ($removeFiles && Storage::disk('local')->exists($file->local_path)) {
                    Storage::disk('local')->delete($file->local_path);
                    $result['physical_files_removed']++;
                }

                // Delete the file record (this will cascade to transactions if foreign key is set)
                $file->delete();
                $result['files_removed']++;

            } catch (Exception $e) {
                $error = "Failed to remove {$file->filename}: {$e->getMessage()}";
                $result['errors'][] = $error;
                $this->error($error);
            }
        }

        $this->info("Removed {$result['files_removed']} database records");
        if ($removeFiles) {
            $this->info("Removed {$result['physical_files_removed']} physical files");
        }

        return $result;
    }

    /**
     * Clean up orphaned transactions
     */
    private function cleanupOrphanedTransactions(bool $dryRun): int
    {
        // Find transactions without corresponding files
        $orphanedCount = DectaTransaction::whereNotExists(function($query) {
            $query->select(\DB::raw(1))
                ->from('decta_files')
                ->whereRaw('decta_files.id = decta_transactions.decta_file_id');
        })->count();

        if ($orphanedCount === 0) {
            $this->info('No orphaned transactions found.');
            return 0;
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would remove {$orphanedCount} orphaned transactions");
            return $orphanedCount;
        }

        if (!$this->option('force') && !$this->confirm("Remove {$orphanedCount} orphaned transactions?")) {
            return 0;
        }

        $removed = DectaTransaction::whereNotExists(function($query) {
            $query->select(\DB::raw(1))
                ->from('decta_files')
                ->whereRaw('decta_files.id = decta_transactions.decta_file_id');
        })->delete();

        $this->info("Removed {$removed} orphaned transactions");
        return $removed;
    }

    /**
     * Display cleanup results
     */
    private function displayCleanupResults(array $results): void
    {
        $this->newLine();
        $this->info('Cleanup Results:');
        $this->info(" - Files removed: {$results['files_removed']}");
        $this->info(" - Transactions removed: {$results['transactions_removed']}");
        $this->info(" - Physical files removed: {$results['physical_files_removed']}");
        $this->info(" - Stuck files reset: {$results['stuck_files_reset']}");

        if (!empty($results['errors'])) {
            $this->error(" - Errors encountered: " . count($results['errors']));
            foreach ($results['errors'] as $error) {
                $this->line("   * {$error}");
            }
        }

        // Show updated statistics
        $this->newLine();
        $this->displayCurrentStats();
    }
}
