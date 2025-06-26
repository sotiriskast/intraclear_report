<?php

namespace Modules\Decta\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class DectaExportService
{
    /**
     * Export data to CSV format
     */
    public function exportToCsv(array $data, string $reportType, array $filters = []): string
    {
        try {
            $filename = $this->generateFilename($reportType, 'csv');
            $filePath = 'exports/' . $filename;
            $fullPath = storage_path('app/' . $filePath);

            Log::info('Starting CSV export', [
                'filename' => $filename,
                'data_count' => count($data),
                'report_type' => $reportType
            ]);

            // Ensure exports directory exists
            $this->ensureExportsDirectory();

            // Generate CSV content
            $csvContent = $this->generateCsvContent($data, $reportType, $filters);

            // Write file directly
            $bytesWritten = file_put_contents($fullPath, $csvContent);

            if ($bytesWritten === false) {
                throw new \Exception("Failed to write CSV file to: {$fullPath}");
            }

            // Verify file exists and has content
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                throw new \Exception("CSV file was not created successfully");
            }

            Log::info('CSV export completed successfully', [
                'file_path' => $filePath,
                'file_size' => filesize($fullPath),
                'bytes_written' => $bytesWritten
            ]);

            return $filePath;

        } catch (\Exception $e) {
            Log::error('CSV export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'report_type' => $reportType,
                'data_count' => count($data)
            ]);
            throw $e;
        }
    }

    /**
     * Ensure exports directory exists
     */
    private function ensureExportsDirectory(): void
    {
        $exportsDir = storage_path('app/exports');
        if (!is_dir($exportsDir)) {
            if (!mkdir($exportsDir, 0755, true)) {
                throw new \Exception("Failed to create exports directory: {$exportsDir}");
            }
        }
    }

    /**
     * Export to Excel (simplified - converts to CSV with .xlsx extension)
     */
    public function exportToExcel(array $data, string $reportType, array $filters = []): string
    {
        // For now, just export as CSV with xlsx extension
        // You can enhance this later with PhpSpreadsheet for true Excel format
        $filename = $this->generateFilename($reportType, 'xlsx');
        $filePath = 'exports/' . $filename;
        $fullPath = storage_path('app/' . $filePath);

        $this->ensureExportsDirectory();

        $csvContent = $this->generateCsvContent($data, $reportType, $filters);
        file_put_contents($fullPath, $csvContent);

        return $filePath;
    }

    /**
     * Generate filename for export
     */
    private function generateFilename(string $reportType, string $extension): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        return "decta_{$reportType}_{$timestamp}.{$extension}";
    }

    /**
     * Generate CSV content
     */
    private function generateCsvContent(array $data, string $reportType, array $filters): string
    {
        try {
            $output = fopen('php://temp', 'r+');

            // Add metadata as comments
            fputcsv($output, ["# Decta {$reportType} Report"]);
            fputcsv($output, ["# Generated: " . Carbon::now()->format('Y-m-d H:i:s')]);
            if (!empty($filters)) {
                fputcsv($output, ["# Filters: " . json_encode($filters)]);
            }
            fputcsv($output, ["# Total Records: " . count($data)]);
            fputcsv($output, [""]); // Empty line

            if (empty($data)) {
                fputcsv($output, ["No data found for the selected criteria"]);
                rewind($output);
                $content = stream_get_contents($output);
                fclose($output);
                return $content;
            }

            // Get headers and add them
            $headers = $this->getHeaders($reportType);
            fputcsv($output, $headers);

            // Add data rows
            foreach ($data as $item) {
                $rowData = $this->formatRowForCsv($item, $reportType);
                fputcsv($output, $rowData);
            }

            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);

            Log::info('CSV content generated successfully', [
                'content_length' => strlen($content),
                'row_count' => count($data) + 5, // +5 for metadata rows
                'report_type' => $reportType
            ]);

            return $content;

        } catch (\Exception $e) {
            Log::error('CSV content generation failed', [
                'error' => $e->getMessage(),
                'report_type' => $reportType,
                'data_count' => count($data)
            ]);
            throw new \Exception("Failed to generate CSV content: " . $e->getMessage());
        }
    }

    /**
     * Get headers based on report type
     */
    private function getHeaders(string $reportType): array
    {
        switch ($reportType) {
            case 'scheme':
                return [
                    'Card Type', 'Transaction Type', 'Currency', 'Amount', 'Net Amount',
                    'Transaction Count', 'Fee', 'Merchant Legal Name', 'Shop ID',
                    'Shop Owner Name', 'Shop Email', 'Shop Website', 'Shop Status', 'Shop Display Name'
                ];

            case 'transactions':
                return [
                    'Payment ID', 'Transaction Date', 'Amount', 'Currency', 'Merchant Name',
                    'Merchant ID', 'Shop ID', 'Terminal ID', 'Card Type', 'Transaction Type', 'Status',
                    'Is Matched', 'Matched At', 'Gateway Transaction ID', 'Error Message'
                ];

            case 'shop_breakdown':
                return [
                    'Shop ID', 'Merchant Name', 'Total Transactions', 'Matched Transactions',
                    'Failed Transactions', 'Approved Transactions', 'Declined Transactions',
                    'Total Amount', 'Matched Amount', 'Average Amount', 'Match Rate %',
                    'Approval Rate %', 'First Transaction', 'Last Transaction',
                    'Currencies Used', 'Currencies List'
                ];

            // Keep all your existing cases...
            case 'merchant_breakdown':
                return [
                    'Merchant ID', 'Merchant Name', 'Total Transactions', 'Matched Transactions',
                    'Failed Transactions', 'Total Amount', 'Matched Amount', 'Avg Amount',
                    'Match Rate %', 'First Transaction', 'Last Transaction', 'Currencies Used',
                    'Terminals Used'
                ];

            case 'volume_breakdown':
                return [
                    'Continent', 'Card Brand', 'Card Type', 'Currency', 'Amount',
                    'Transaction Count', 'Percentage of Total'
                ];

            case 'daily_summary':
                return [
                    'Date', 'Total Transactions', 'Matched Count', 'Unmatched Count',
                    'Failed Count', 'Total Amount', 'Matched Amount', 'Unique Merchants',
                    'Avg Transaction Amount', 'Match Rate %'
                ];

            default:
                return !empty($data) && is_array($data[0]) ? array_keys($data[0]) : ['Data'];
        }
    }

    /**
     * Format row data for CSV export
     */
    /**
     * Format row data for CSV export - FIXED shop_breakdown
     */
    private function formatRowForCsv(array $item, string $reportType): array
    {
        try {
            switch ($reportType) {
                case 'scheme':
                    return [
                        $item['card_type'] ?? '',
                        $item['transaction_type_name'] ?? $item['transaction_type'] ?? '',
                        $item['currency'] ?? '',
                        $item['amount'] ?? 0,
                        $item['net_amount'] ?? 0,
                        $item['count'] ?? 0,
                        $item['fee'] ?? 0,
                        $item['merchant_legal_name'] ?? '',
                        $item['shop_id'] ?? '',
                        // ADDED: Shop details
                        $item['shop_details']['owner_name'] ?? '',
                        $item['shop_details']['email'] ?? '',
                        $item['shop_details']['website'] ?? '',
                        $item['shop_details']['status'] ?? '',
                        $item['shop_details']['display_name'] ?? ''
                    ];

                case 'transactions':
                    return [
                        $item['payment_id'] ?? '',
                        $item['transaction_date'] ?? '',
                        $item['amount'] ?? 0,
                        $item['currency'] ?? '',
                        $item['merchant_name'] ?? '',
                        $item['merchant_id'] ?? '',
                        $item['shop_id'] ?? '',
                        $item['terminal_id'] ?? '',
                        $item['card_type'] ?? '',
                        $item['transaction_type'] ?? '',
                        $item['status'] ?? '',
                        isset($item['is_matched']) && $item['is_matched'] ? 'Yes' : 'No',
                        $item['matched_at'] ?? '',
                        $item['gateway_info']['transaction_id'] ?? '',
                        $item['error_message'] ?? ''
                    ];

                case 'shop_breakdown':
                    return [
                        $item['shop_id'] ?? '',
                        $item['merchant_name'] ?? '',
                        $item['total_transactions'] ?? 0,
                        $item['matched_transactions'] ?? 0,
                        $item['failed_transactions'] ?? 0,
                        $item['approved_transactions'] ?? 0,
                        $item['declined_transactions'] ?? 0,
                        $item['total_amount'] ?? 0,
                        $item['matched_amount'] ?? 0,
                        $item['avg_amount'] ?? 0,
                        $item['match_rate'] ?? 0,
                        $item['approval_rate'] ?? 0,
                        $item['first_transaction'] ?? '',
                        $item['last_transaction'] ?? '',
                        $item['currencies_used'] ?? 0,
                        is_array($item['currencies_list'] ?? null) ? implode(', ', $item['currencies_list']) : ''
                    ];

                // ... keep other existing cases unchanged ...

                default:
                    return array_values($item);
            }
        } catch (\Exception $e) {
            Log::error('Row formatting failed', [
                'error' => $e->getMessage(),
                'report_type' => $reportType,
                'item' => $item
            ]);
            return array_fill(0, count($this->getHeaders($reportType)), '');
        }
    }
    /**
     * Setup Excel sheet with metadata
     */
    private function setupExcelSheet($sheet, string $reportType, array $filters): void
    {
        $sheet->setTitle(ucfirst($reportType) . ' Report');

        // Add metadata at the top
        $sheet->setCellValue('A1', 'Decta ' . ucfirst($reportType) . ' Report');
        $sheet->setCellValue('A2', 'Generated: ' . Carbon::now()->format('Y-m-d H:i:s'));

        $row = 3;
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                $sheet->setCellValue('A' . $row, ucfirst(str_replace('_', ' ', $key)) . ': ' . $value);
                $row++;
            }
        }

        // Add empty row before headers
        $this->headerStartRow = $row + 1;
    }

    /**
     * Add headers to Excel sheet
     */
    private function addHeaders($sheet, array $headers): void
    {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $this->headerStartRow, $header);
            $col++;
        }
    }

    /**
     * Add data to Excel sheet
     */
    private function addDataToSheet($sheet, array $data, string $reportType, int $headerCount): void
    {
        $row = $this->headerStartRow + 1;

        foreach ($data as $item) {
            $rowData = $this->formatRowForCsv($item, $reportType);
            $col = 'A';

            foreach ($rowData as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }
    }

    /**
     * Apply styling to Excel sheet
     */
    private function applyExcelStyling($sheet, int $dataRows, int $headerCount): void
    {
        // Title styling
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        // Header styling
        $headerRange = 'A' . $this->headerStartRow . ':' . chr(64 + $headerCount) . $this->headerStartRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB');

        // Auto-size columns
        foreach (range('A', chr(64 + $headerCount)) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders to data range
        if ($dataRows > 0) {
            $dataRange = 'A' . $this->headerStartRow . ':' . chr(64 + $headerCount) . ($this->headerStartRow + $dataRows);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $filePath): bool
    {
        return Storage::exists($filePath);
    }

    /**
     * Get file size
     */
    public function getFileSize(string $filePath): int
    {
        return Storage::size($filePath);
    }

    /**
     * Delete export file
     */
    public function deleteExport(string $filePath): bool
    {
        return Storage::delete($filePath);
    }
}
