<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class DectaDownloadFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:download-files
                            {--directory= : Optional remote directory to download from}
                            {--force : Force download even if files were already processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download files from the Decta SFTP server';

    /**
     * @var DectaSftpService
     */
    protected $sftpService;

    /**
     * @var DectaFileRepository
     */
    protected $fileRepository;

    /**
     * Create a new command instance.
     */
    public function __construct(DectaSftpService $sftpService, DectaFileRepository $fileRepository)
    {
        parent::__construct();
        $this->sftpService = $sftpService;
        $this->fileRepository = $fileRepository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Decta file download process...');

        try {
            $directory = $this->option('directory') ?? '';
            $force = $this->option('force') ?? false;

            // List files from SFTP
            $files = $this->sftpService->listFiles($directory);

            if (empty($files)) {
                $this->info('No files found on the SFTP server.');
                return 0;
            }

            $this->info(sprintf('Found %d files on the SFTP server.', count($files)));

            $downloadedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            $progressBar = $this->output->createProgressBar(count($files));
            $progressBar->start();

            foreach ($files as $file) {
                $filename = basename($file['path']);

                // Check if file was already processed
                if (!$force && $this->fileRepository->existsByFilename($filename)) {
                    $this->info(" - Skipped file: {$filename} (already exists)");
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                try {
                    // Determine local path
                    $localPath = config('decta.sftp.local_path') . '/' . $filename;

                    // Download the file
                    $success = $this->sftpService->downloadFile($file['path'], $localPath);

                    if ($success) {
                        // Get file info
                        $fileSize = Storage::disk('local')->size($localPath);
                        $fileType = pathinfo($filename, PATHINFO_EXTENSION);

                        // Create file record
                        $this->fileRepository->create([
                            'filename' => $filename,
                            'original_path' => $file['path'],
                            'local_path' => $localPath,
                            'file_size' => $fileSize,
                            'file_type' => $fileType,
                            'status' => DectaFile::STATUS_PENDING,
                            'metadata' => [
                                'last_modified' => $file['lastModified'] ?? null,
                                'visibility' => $file['visibility'] ?? null,
                            ],
                        ]);

                        $downloadedCount++;
                    } else {
                        $errorCount++;
                        $this->error(" - Failed to download file: {$filename}");
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error('Error downloading file from SFTP', [
                        'file' => $filename,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->error(" - Error downloading file: {$filename} - {$e->getMessage()}");
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Download process completed:");
            $this->info(" - Downloaded: {$downloadedCount} files");
            $this->info(" - Skipped: {$skippedCount} files");
            $this->info(" - Errors: {$errorCount} files");

            if ($errorCount > 0) {
                return 1;
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Failed to download files: {$e->getMessage()}");
            Log::error('Failed to download files from SFTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
