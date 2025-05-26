<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Log;
use Exception;

class DectaProcessFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:process-files
                            {--limit=10 : Limit the number of files to process}
                            {--type= : Only process files of a specific type (e.g., csv, xml)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process downloaded Decta files';

    /**
     * @var DectaFileRepository
     */
    protected $fileRepository;

    /**
     * @var DectaSftpService
     */
    protected $sftpService;

    /**
     * Create a new command instance.
     */
    public function __construct(DectaFileRepository $fileRepository, DectaSftpService $sftpService)
    {
        parent::__construct();
        $this->fileRepository = $fileRepository;
        $this->sftpService = $sftpService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Decta file processing...');

        try {
            $limit = (int) $this->option('limit');
            $type = $this->option('type');

            // Get pending files
            $query = DectaFile::pending();

            if ($type) {
                $query->where('file_type', $type);
            }

            $files = $query->limit($limit)->get();

            if ($files->isEmpty()) {
                $this->info('No pending files to process.');
                return 0;
            }

            $this->info(sprintf('Found %d pending files to process.', $files->count()));

            $processedCount = 0;
            $failedCount = 0;

            $progressBar = $this->output->createProgressBar($files->count());
            $progressBar->start();

            foreach ($files as $file) {
                try {
                    // Mark file as processing
                    $file->markAsProcessing();

                    // Process the file based on file type
                    $success = $this->processFile($file);

                    if ($success) {
                        // Mark file as processed and move to processed directory
                        $file->markAsProcessed();
                        $this->sftpService->moveToProcessed($file->local_path);
                        $processedCount++;
                    } else {
                        // Mark file as failed and move to failed directory
                        $file->markAsFailed('Failed to process file');
                        $this->sftpService->moveToFailed($file->local_path);
                        $failedCount++;
                    }
                } catch (Exception $e) {
                    // Handle exception
                    $file->markAsFailed($e->getMessage());
                    $this->sftpService->moveToFailed($file->local_path);
                    $failedCount++;

                    Log::error('Error processing file', [
                        'file' => $file->filename,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Processing completed:");
            $this->info(" - Processed: {$processedCount} files");
            $this->info(" - Failed: {$failedCount} files");

            if ($failedCount > 0) {
                return 1;
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Failed to process files: {$e->getMessage()}");
            Log::error('Failed to process files', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Process a file based on its type
     *
     * @param DectaFile $file
     * @return bool
     */
    protected function processFile(DectaFile $file): bool
    {
        // Get file content
        $content = $this->fileRepository->getFileContent($file);

        if (!$content) {
            return false;
        }

        // Process based on file type
        switch ($file->file_type) {
            case 'csv':
                return $this->processCsvFile($file, $content);
            case 'xml':
                return $this->processXmlFile($file, $content);
            case 'txt':
                return $this->processTxtFile($file, $content);
            default:
                // Default handling for other file types
                $this->info(" - Unsupported file type: {$file->file_type}");
                return false;
        }
    }

    /**
     * Process CSV file
     *
     * @param DectaFile $file
     * @param string $content
     * @return bool
     */
    protected function processCsvFile(DectaFile $file, string $content): bool
    {
        $this->info(" - Processing CSV file: {$file->filename}");

        try {
            // Parse CSV data
            $rows = array_map('str_getcsv', explode("\n", $content));

            // Extract headers from first row
            $headers = array_shift($rows);

            if (empty($headers)) {
                return false;
            }

            // Process CSV data
            // This is where you would implement your specific processing logic
            // For now, we'll just log the number of rows
            Log::info('CSV file processed', [
                'file' => $file->filename,
                'rows' => count($rows),
                'headers' => $headers,
            ]);

            // Example: You would store data in your database here
            // ...

            return true;
        } catch (Exception $e) {
            Log::error('Error processing CSV file', [
                'file' => $file->filename,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Process XML file
     *
     * @param DectaFile $file
     * @param string $content
     * @return bool
     */
    protected function processXmlFile(DectaFile $file, string $content): bool
    {
        $this->info(" - Processing XML file: {$file->filename}");

        try {
            // Parse XML data
            $xml = simplexml_load_string($content);

            if (!$xml) {
                return false;
            }

            // Process XML data
            // This is where you would implement your specific processing logic
            // For now, we'll just log the XML name
            Log::info('XML file processed', [
                'file' => $file->filename,
                'root_element' => $xml->getName(),
            ]);

            // Example: You would store data in your database here
            // ...

            return true;
        } catch (Exception $e) {
            Log::error('Error processing XML file', [
                'file' => $file->filename,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Process TXT file
     *
     * @param DectaFile $file
     * @param string $content
     * @return bool
     */
    protected function processTxtFile(DectaFile $file, string $content): bool
    {
        $this->info(" - Processing TXT file: {$file->filename}");

        try {
            // Process text data
            // This is where you would implement your specific processing logic
            // For now, we'll just log the line count
            $lines = explode("\n", $content);

            Log::info('TXT file processed', [
                'file' => $file->filename,
                'lines' => count($lines),
            ]);

            // Example: You would store data in your database here
            // ...

            return true;
        } catch (Exception $e) {
            Log::error('Error processing TXT file', [
                'file' => $file->filename,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
