<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaSftpService;
use Illuminate\Support\Facades\Log;
use Exception;

class DectaTestLatestFileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:test-latest-file
                            {--days-back=7 : Number of days to look back for files}
                            {--download : Actually download the file (default: just list)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test retrieving the latest file from Decta SFTP with date-based folder structure';

    /**
     * @var DectaSftpService
     */
    protected $sftpService;

    /**
     * Create a new command instance.
     */
    public function __construct(DectaSftpService $sftpService)
    {
        parent::__construct();
        $this->sftpService = $sftpService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing latest file retrieval from Decta SFTP...');

        try {
            $daysBack = (int) $this->option('days-back');
            $shouldDownload = $this->option('download');

            // Use the service method to find the latest file
            $latestFile = $this->sftpService->findLatestFile($daysBack);

            if (!$latestFile) {
                $this->error('No files found matching the expected pattern.');
                return 1;
            }

            $this->info("Latest file found:");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Remote Path', $latestFile['path']],
                    ['File Size', $this->formatBytes($latestFile['fileSize'])],
                    ['File Date', $latestFile['formatted_date']],
                    ['Date Code', $latestFile['file_date']],
                    ['Pattern', $latestFile['pattern_matched']],
                ]
            );

            if ($shouldDownload) {
                $this->info('Downloading the file...');

                $filename = basename($latestFile['path']);
                $localPath = 'decta/test/' . $filename;

                $success = $this->sftpService->downloadFile($latestFile['path'], $localPath);

                if ($success) {
                    $fullPath = storage_path('app/' . $localPath);
                    $this->info("File downloaded successfully to: {$fullPath}");
                    $this->info("File size: " . $this->formatBytes(filesize($fullPath)));

                    // Show first few lines if it's a CSV
                    if (pathinfo($filename, PATHINFO_EXTENSION) === 'csv') {
                        $this->showCsvPreview($fullPath);
                    }
                } else {
                    $this->error('Failed to download the file.');
                    return 1;
                }
            } else {
                $this->info('Use --download flag to actually download the file.');
            }

            return 0;

        } catch (Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('Error in test latest file command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Show CSV file preview
     *
     * @param string $filePath Path to the CSV file
     */
    protected function showCsvPreview(string $filePath): void
    {
        $this->info("\nCSV Preview (first 5 rows):");

        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                $this->error('Could not open CSV file for preview.');
                return;
            }

            $rowCount = 0;
            $headers = null;

            while (($data = fgetcsv($handle)) !== false && $rowCount < 6) {
                if ($rowCount === 0) {
                    $headers = $data;
                    $this->line("Headers: " . implode(' | ', $headers));
                    $this->line(str_repeat('-', 80));
                } else {
                    $this->line("Row {$rowCount}: " . implode(' | ', array_slice($data, 0, 5)) . (count($data) > 5 ? ' ...' : ''));
                }
                $rowCount++;
            }

            fclose($handle);

            // Count total rows
            $totalRows = count(file($filePath)) - 1; // Subtract header
            $this->info("Total rows: {$totalRows}");

        } catch (Exception $e) {
            $this->error("Error reading CSV: {$e->getMessage()}");
        }
    }

    /**
     * Format bytes to a human-readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
