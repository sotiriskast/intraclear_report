<?php


namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Models\DectaFile;
use Modules\Decta\Services\VisaSmsService;
use Illuminate\Support\Facades\DB;
use Exception;

class VisaSmsFixConflictsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:fix-conflicts
                            {--dry-run : Show what would be fixed without making changes}
                            {--force : Force fix even if no conflicts detected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix status conflicts between Visa SMS files and regular Decta processing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Fixing Visa SMS / Decta Processing Conflicts');
        $this->line('===============================================');

        try {
            $isDryRun = $this->option('dry-run');
            $force = $this->option('force');

            // Find Visa SMS files with regular statuses
            $conflictingFiles = $this->findConflictingFiles();

            if (empty($conflictingFiles) && !$force) {
                $this->info('✅ No conflicts found! All Visa SMS files have correct statuses.');
                return Command::SUCCESS;
            }

            $this->info("Found " . count($conflictingFiles) . " files with status conflicts");

            if ($isDryRun) {
                $this->info('🔍 DRY RUN MODE - No changes will be made');
                $this->line('');
                $this->displayConflicts($conflictingFiles);
                return Command::SUCCESS;
            }

            // Fix the conflicts
            $results = $this->fixConflicts($conflictingFiles);

            $this->displayResults($results);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("💥 Error fixing conflicts: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Find files with status conflicts
     */
    protected function findConflictingFiles(): array
    {
        // Find Visa SMS files that have regular Decta statuses
        return DectaFile::where(function ($query) {
            $query->where('file_type', 'visa_sms_csv')
                ->orWhere('filename', 'LIKE', 'INTCL_visa_sms_tr_det_%');
        })
            ->whereIn('status', ['pending', 'processing', 'processed', 'failed'])
            ->get()
            ->toArray();
    }

    /**
     * Fix the status conflicts
     */
    protected function fixConflicts(array $files): array
    {
        $results = [
            'fixed' => 0,
            'failed' => 0,
            'details' => []
        ];

        DB::beginTransaction();

        try {
            foreach ($files as $fileData) {
                $file = DectaFile::find($fileData['id']);
                if (!$file) {
                    continue;
                }

                $oldStatus = $file->status;
                $newStatus = $this->mapToVisaStatus($oldStatus);

                if ($newStatus) {
                    $file->update([
                        'status' => $newStatus,
                        'file_type' => 'visa_sms_csv', // Ensure correct file type
                    ]);

                    $results['fixed']++;
                    $results['details'][] = [
                        'filename' => $file->filename,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'success' => true
                    ];

                    $this->line("✅ Fixed: {$file->filename} ({$oldStatus} → {$newStatus})");
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'filename' => $file->filename,
                        'old_status' => $oldStatus,
                        'new_status' => null,
                        'success' => false,
                        'error' => 'Unknown status mapping'
                    ];

                    $this->line("❌ Failed: {$file->filename} (unknown status: {$oldStatus})");
                }
            }

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Map regular status to Visa SMS status
     */
    protected function mapToVisaStatus(string $status): ?string
    {
        return match ($status) {
            'pending' => VisaSmsService::STATUS_VISA_PENDING,
            'processing' => VisaSmsService::STATUS_VISA_PROCESSING,
            'processed' => VisaSmsService::STATUS_VISA_PROCESSED,
            'failed' => VisaSmsService::STATUS_VISA_FAILED,
            default => null
        };
    }

    /**
     * Display conflicts found
     */
    protected function displayConflicts(array $files): void
    {
        if (empty($files)) {
            return;
        }

        $this->info('Files with status conflicts:');
        $this->line('');

        $tableData = [];
        foreach ($files as $file) {
            $newStatus = $this->mapToVisaStatus($file['status']);
            $tableData[] = [
                $file['filename'],
                $file['status'],
                $newStatus ?? 'Unknown',
                $file['file_type'] ?? 'Not set'
            ];
        }

        $this->table(
            ['Filename', 'Current Status', 'Should Be', 'File Type'],
            $tableData
        );
    }

    /**
     * Display fix results
     */
    protected function displayResults(array $results): void
    {
        $this->line('');
        $this->info('📊 Fix Results');
        $this->line('==============');
        $this->line("Files fixed: {$results['fixed']}");
        $this->line("Files failed: {$results['failed']}");

        if ($results['failed'] > 0) {
            $this->line('');
            $this->warn('Some files could not be fixed. Check the output above for details.');
        }

        if ($results['fixed'] > 0) {
            $this->line('');
            $this->info('✅ Status conflicts have been resolved!');
            $this->line('You can now run:');
            $this->line('- php artisan visa:process-sms-reports (for Visa SMS files)');
            $this->line('- php artisan decta:process-files (for regular Decta files)');
            $this->line('');
            $this->line('These commands will no longer interfere with each other.');
        }
    }
}
