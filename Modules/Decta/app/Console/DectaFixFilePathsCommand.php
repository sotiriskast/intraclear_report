<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Log;

class DectaFixFilePathsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:fix-file-paths
                            {--file-id= : Fix a specific file by ID}
                            {--status= : Fix files with specific status (failed, processed, etc.)}
                            {--dry-run : Show what would be fixed without actually fixing}
                            {--validate : Validate file integrity after fixing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix file paths in the database for Decta files that have been moved';

    /**
     * @var DectaFileRepository
     */
    protected $fileRepository;

    /**
     * Create a new command instance.
     */
    public function __construct(DectaFileRepository $fileRepository)
    {
        parent::__construct();
        $this->fileRepository = $fileRepository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Decta file path fixing process...');

        $fileId = $this->option('file-id');
        $status = $this->option('status');
        $dryRun = $this->option('dry-run');
        $validate = $this->option('validate');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be made to the database');
        }

        // Get files to process
        $files = $this->getFilesToFix($fileId, $status);

        if ($files->isEmpty()) {
            $this->info('No files found to fix.');
            return 0;
        }

        $this->info(sprintf('Found %d file(s) to check.', $files->count()));

        $fixedCount = 0;
        $issuesFound = 0;
        $validationErrors = 0;

        $progressBar = $this->output->createProgressBar($files->count());
        $progressBar->start();

        foreach ($files as $file) {
            try {
                $result = $this->processFile($file, $dryRun, $validate);

                if ($result['fixed']) {
                    $fixedCount++;
                }

                if ($result['has_issues']) {
                    $issuesFound++;
                }

                if ($result['validation_failed']) {
                    $validationErrors++;
                }

            } catch (\Exception $e) {
                $this->error("Error processing file {$file->filename}: {$e->getMessage()}");
                Log::error('Error in file path fixing', [
                    'file_id' => $file->id,
                    'filename' => $file->filename,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->displaySummary($fixedCount, $issuesFound, $validationErrors, $dryRun);

        return $issuesFound > 0 ? 1 : 0;
    }

    /**
     * Get files to fix based on options
     */
    private function getFilesToFix(?string $fileId, ?string $status)
    {
        if ($fileId) {
            $file = $this->fileRepository->findById((int) $fileId);
            return collect($file ? [$file] : []);
        }

        if ($status) {
            return $this->fileRepository->getFilesByStatus($status);
        }

        // Get all files that might need fixing (failed, processed, processing)
        return DectaFile::whereIn('status', [
            DectaFile::STATUS_FAILED,
            DectaFile::STATUS_PROCESSED,
            DectaFile::STATUS_PROCESSING
        ])->get();
    }

    /**
     * Process individual file
     */
    private function processFile(DectaFile $file, bool $dryRun, bool $validate): array
    {
        $result = [
            'fixed' => false,
            'has_issues' => false,
            'validation_failed' => false,
            'original_path' => $file->local_path,
            'new_path' => null
        ];

        // Check if file exists at current path
        $currentPathExists = \Storage::disk('decta')->exists($file->local_path);

        if ($currentPathExists) {
            $this->line("✓ {$file->filename} - Path correct");

            if ($validate) {
                $validation = $this->fileRepository->validateFileIntegrity($file);
                if (!empty($validation['issues'])) {
                    $result['validation_failed'] = true;
                    $result['has_issues'] = true;
                    $this->warn("  ! Validation issues: " . implode(', ', $validation['issues']));
                }
            }

            return $result;
        }

        // Try to find the actual file path
        $actualPath = $this->fileRepository->findActualFilePath($file);

        if ($actualPath) {
            $result['new_path'] = $actualPath;
            $result['has_issues'] = true;

            if ($actualPath !== $file->local_path) {
                $this->warn("⚠ {$file->filename} - Found at different location");
                $this->line("  Old: {$file->local_path}");
                $this->line("  New: {$actualPath}");

                if (!$dryRun) {
                    $file->update(['local_path' => $actualPath]);
                    $result['fixed'] = true;
                    $this->info("  ✓ Database updated");

                    Log::info('File path fixed', [
                        'file_id' => $file->id,
                        'filename' => $file->filename,
                        'old_path' => $result['original_path'],
                        'new_path' => $actualPath
                    ]);
                } else {
                    $this->info("  → Would update database path");
                }

                if ($validate) {
                    $validation = $this->fileRepository->validateFileIntegrity($file);
                    if (!empty($validation['issues'])) {
                        $result['validation_failed'] = true;
                        $this->warn("  ! Validation issues: " . implode(', ', $validation['issues']));
                    } else {
                        $this->info("  ✓ File validation passed");
                    }
                }
            }
        } else {
            $result['has_issues'] = true;
            $this->error("✗ {$file->filename} - File not found in any expected location");

            // Show where we looked
            $searchedPaths = $this->getPossiblePaths($file->local_path);
            $this->line("  Searched paths:");
            foreach ($searchedPaths as $path) {
                $this->line("    - {$path}");
            }
        }

        return $result;
    }

    /**
     * Get all possible paths where a file might be located
     */
    private function getPossiblePaths(string $originalPath): array
    {
        $filename = basename($originalPath);
        $directory = dirname($originalPath);
        $failedDir = config('decta.files.failed_dir', 'failed');
        $processedDir = config('decta.files.processed_dir', 'processed');

        return [
            $originalPath,
            $directory . '/' . $failedDir . '/' . $filename,
            $directory . '/' . $processedDir . '/' . $filename,
            'files/' . $filename,
            'files/' . $failedDir . '/' . $filename,
            'files/' . $processedDir . '/' . $filename
        ];
    }

    /**
     * Display summary of results
     */
    private function displaySummary(int $fixedCount, int $issuesFound, int $validationErrors, bool $dryRun): void
    {
        $this->info("File path fixing completed:");

        if ($dryRun) {
            $this->info(" - Files that would be fixed: {$fixedCount}");
        } else {
            $this->info(" - Files fixed: {$fixedCount}");
        }

        $this->info(" - Files with issues: {$issuesFound}");

        if ($validationErrors > 0) {
            $this->warn(" - Files with validation errors: {$validationErrors}");
        }

        if ($issuesFound > 0 && $dryRun) {
            $this->newLine();
            $this->info("To actually fix the issues, run the command without --dry-run");
        }

        if ($validationErrors > 0) {
            $this->newLine();
            $this->warn("Some files have validation issues that may need manual attention.");
        }

        if ($fixedCount > 0 && !$dryRun) {
            $this->newLine();
            $this->info("You can now retry processing the fixed files with:");
            $this->info("php artisan decta:process-files --retry-failed");
        }
    }
}
