<?php

namespace App\Services;

use ZipArchive;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ZipExportService
{
    public function __construct(
        private readonly DynamicLogger $logger
    ) {}

    /**
     * Create a ZIP file containing settlement reports maintaining directory structure
     *
     * @param array $filePaths Array of file paths to include in the ZIP
     * @param array $dateRange Date range for the settlement period
     * @return string Path to the created ZIP file
     */
    public function createZip(array $filePaths, array $dateRange): string
    {
        try {
            // Create directory for ZIP files
            $dateFolder = Carbon::now()->format('Y-m-d');
            $relativePath = sprintf('reports/%s/zips', $dateFolder);
            $fullPath = storage_path("app/{$relativePath}");

            if (!file_exists($fullPath)) {
                mkdir($fullPath, 0755, true);
            }

            // Generate ZIP filename with date range
            $zipName = sprintf(
                'settlement_reports_%s_to_%s.zip',
                Carbon::parse($dateRange['start'])->setTimeFromTimeString(now()->format('H:i:s'))->format('Y-m-d H:i:s'),
                Carbon::parse($dateRange['end'])->setTimeFromTimeString(now()->format('H:i:s'))->format('Y-m-d H:i:s')

            );
            $zipPath = "{$fullPath}/{$zipName}";

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Cannot create ZIP file");
            }

            foreach ($filePaths as $filePath) {
                $fullFilePath = storage_path("app/{$filePath}");
                if (file_exists($fullFilePath)) {
                    // Get merchant details from the path structure
                    preg_match('/reports\/[\d-]+\/([^\/]+)\//', $filePath, $matches);
                    $merchantFolder = $matches[1] ?? 'unknown_merchant';

                    // Add file to ZIP maintaining the merchant folder structure
                    $filename = basename($filePath);
                    $pathInZip = "{$merchantFolder}/{$filename}";

                    $zip->addFile($fullFilePath, $pathInZip);
                }
            }

            $zip->close();

            // Log successful ZIP creation
            $this->logger->log('info', 'Settlement reports ZIP created', [
                'zip_path' => $zipPath,
                'file_count' => count($filePaths),
                'date_range' => $dateRange,
            ]);

            return "{$relativePath}/{$zipName}";

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to create settlement reports ZIP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
