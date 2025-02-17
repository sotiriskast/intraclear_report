<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Carbon\Carbon;

/**
 * Service for creating ZIP archives of settlement reports
 *
 * @property DynamicLogger $logger Service for logging export operations
 *
 * This service handles:
 * - Creating ZIP archives of multiple settlement reports
 * - Maintaining directory structure within ZIPs
 * - Proper file storage and cleanup
 */
class ZipExportService
{
    public function __construct(
        private readonly DynamicLogger $logger
    )
    {
    }

    /**
     * Create a ZIP file containing settlement reports maintaining directory structure
     *
     * @param array $filePaths Array of file paths to include in the ZIP
     * @param array $dateRange Date range for the settlement period ['start' => 'Y-m-d', 'end' => 'Y-m-d']
     * @return string Path to the created ZIP file
     * @throws \Exception If ZIP creation fails
     */
    public function createZip(array $filePaths, array $dateRange): string
    {
        try {
            // Create a temporary stream for the ZIP
            $tempStream = fopen('php://temp', 'r+');

            $zip = new ZipArchive();
            $tempFile = tempnam(sys_get_temp_dir(), 'settlement_zip_');

            if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Cannot create ZIP file");
            }

            foreach ($filePaths as $filePath) {
                if (Storage::exists($filePath)) {
                    // Get merchant details from the path structure
                    preg_match('/reports\/[\d-]+\/([^\/]+)\//', $filePath, $matches);
                    $merchantFolder = $matches[1] ?? 'unknown_merchant';

                    // Add file to ZIP maintaining the merchant folder structure
                    $filename = basename($filePath);
                    $pathInZip = "{$merchantFolder}/{$filename}";

                    // Get file content from Storage and add to ZIP
                    $content = Storage::get($filePath);
                    $zip->addFromString($pathInZip, $content);
                }
            }

            $zip->close();

            // Read the ZIP file into the stream
            $zipContent = file_get_contents($tempFile);

            // Generate storage path for ZIP
            $relativePath = $this->generateZipPath($dateRange);

            // Store the ZIP file in S3
            Storage::put($relativePath, $zipContent);

            // Clean up temp file
            unlink($tempFile);

            $this->logger->log('info', 'Settlement reports ZIP created', [
                'zip_path' => $relativePath,
                'file_count' => count($filePaths),
                'date_range' => $dateRange,
            ]);

            return $relativePath;

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to create settlement reports ZIP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate standardized path for ZIP files
     *
     * @param array $dateRange Date range ['start' => 'Y-m-d', 'end' => 'Y-m-d']
     * @return string Generated path for the ZIP file
     */
    private function generateZipPath(array $dateRange): string
    {
        $dateFolder = Carbon::now()->format('Y-m-d');

        return sprintf(
            'reports/%s/zips/settlement_reports_%s_to_%s.zip',
            $dateFolder,
            Carbon::parse($dateRange['start'])->format('Y-m-d'),
            Carbon::parse($dateRange['end'])->format('Y-m-d')
        );
    }
}
