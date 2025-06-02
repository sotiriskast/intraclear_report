<?php

namespace Modules\Decta\Services;

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
        $filename = $this->generateFilename($reportType, 'csv');
        $filePath = 'exports/' . $filename;

        // Create CSV content
        $csvContent = $this->generateCsvContent($data, $reportType, $filters);

        // Store file
        Storage::put($filePath, $csvContent);

        return $filePath;
    }

    /**
     * Export data to Excel format
     */
    public function exportToExcel(array $data, string $reportType, array $filters = []): string
    {
        $filename = $this->generateFilename($reportType, 'xlsx');
        $filePath = 'exports/' . $filename;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set up the spreadsheet
        $this->setupExcelSheet($sheet, $reportType, $filters);

        // Add headers
        $headers = $this->getHeaders($reportType);
        $this->addHeaders($sheet, $headers);

        // Add data
        $this->addDataToSheet($sheet, $data, $reportType, count($headers));

        // Apply styling
        $this->applyExcelStyling($sheet, count($data), count($headers));

        // Save file
        $writer = new Xlsx($spreadsheet);
        $fullPath = storage_path('app/' . $filePath);

        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer->save($fullPath);

        return $filePath;
    }

    /**
     * Export data to JSON format
     */
    public function exportToJson(array $data, string $reportType, array $filters = []): string
    {
        $filename = $this->generateFilename($reportType, 'json');
        $filePath = 'exports/' . $filename;

        $exportData = [
            'report_type' => $reportType,
            'generated_at' => Carbon::now()->toISOString(),
            'filters_applied' => $filters,
            'total_records' => count($data),
            'data' => $data
        ];

        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        Storage::put($filePath, $jsonContent);

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
        $headers = $this->getHeaders($reportType);
        $rows = [];

        // Add metadata header
        $rows[] = "# Decta {$reportType} Report";
        $rows[] = "# Generated: " . Carbon::now()->format('Y-m-d H:i:s');
        if (!empty($filters)) {
            $rows[] = "# Filters: " . json_encode($filters);
        }
        $rows[] = "# Total Records: " . count($data);
        $rows[] = ""; // Empty line

        // Add column headers
        $rows[] = '"' . implode('","', $headers) . '"';

        // Add data rows
        foreach ($data as $item) {
            $rowData = $this->formatRowForCsv($item, $reportType);
            $rows[] = '"' . implode('","', array_map(function($value) {
                    return str_replace('"', '""', $value); // Escape quotes
                }, $rowData)) . '"';
        }

        return implode("\n", $rows);
    }

    /**
     * Get headers based on report type
     */
    private function getHeaders(string $reportType): array
    {
        switch ($reportType) {
            case 'transactions':
                return [
                    'Payment ID', 'Transaction Date', 'Amount', 'Currency', 'Merchant Name',
                    'Merchant ID', 'Terminal ID', 'Card Type', 'Transaction Type', 'Status',
                    'Is Matched', 'Matched At', 'Gateway Transaction ID', 'Error Message'
                ];

            case 'daily_summary':
                return [
                    'Date', 'Total Transactions', 'Matched Count', 'Unmatched Count',
                    'Failed Count', 'Total Amount', 'Matched Amount', 'Unique Merchants',
                    'Avg Transaction Amount', 'Match Rate %'
                ];

            case 'merchant_breakdown':
                return [
                    'Merchant ID', 'Merchant Name', 'Total Transactions', 'Matched Transactions',
                    'Failed Transactions', 'Total Amount', 'Matched Amount', 'Avg Amount',
                    'Match Rate %', 'First Transaction', 'Last Transaction', 'Currencies Used',
                    'Terminals Used'
                ];

            case 'matching':
                return [
                    'Status', 'Is Matched', 'Transaction Count', 'Avg Matching Time (minutes)',
                    'Has Matching Attempts'
                ];

            case 'settlements':
                return [
                    'Date', 'Merchant ID', 'Merchant Name', 'Transaction Count', 'Gross Amount',
                    'Settled Count', 'Settled Amount', 'Unsettled Amount'
                ];

            default:
                return ['Data'];
        }
    }

    /**
     * Format row data for CSV export
     */
    private function formatRowForCsv(array $item, string $reportType): array
    {
        switch ($reportType) {
            case 'transactions':
                return [
                    $item['payment_id'] ?? '',
                    $item['transaction_date'] ?? '',
                    $item['amount'] ?? 0,
                    $item['currency'] ?? '',
                    $item['merchant_name'] ?? '',
                    $item['merchant_id'] ?? '',
                    $item['terminal_id'] ?? '',
                    $item['card_type'] ?? '',
                    $item['transaction_type'] ?? '',
                    $item['status'] ?? '',
                    $item['is_matched'] ? 'Yes' : 'No',
                    $item['matched_at'] ?? '',
                    $item['gateway_info']['transaction_id'] ?? '',
                    $item['error_message'] ?? ''
                ];

            case 'daily_summary':
                return [
                    $item['date'] ?? '',
                    $item['total_transactions'] ?? 0,
                    $item['matched_count'] ?? 0,
                    $item['unmatched_count'] ?? 0,
                    $item['failed_count'] ?? 0,
                    $item['total_amount'] ?? 0,
                    $item['matched_amount'] ?? 0,
                    $item['unique_merchants'] ?? 0,
                    $item['avg_transaction_amount'] ?? 0,
                    $item['match_rate'] ?? 0
                ];

            case 'merchant_breakdown':
                return [
                    $item['merchant_id'] ?? '',
                    $item['merchant_name'] ?? '',
                    $item['total_transactions'] ?? 0,
                    $item['matched_transactions'] ?? 0,
                    $item['failed_transactions'] ?? 0,
                    $item['total_amount'] ?? 0,
                    $item['matched_amount'] ?? 0,
                    $item['avg_amount'] ?? 0,
                    $item['match_rate'] ?? 0,
                    $item['first_transaction'] ?? '',
                    $item['last_transaction'] ?? '',
                    $item['currencies_used'] ?? 0,
                    $item['terminals_used'] ?? 0
                ];

            case 'matching':
                return [
                    $item['status'] ?? '',
                    $item['is_matched'] ? 'Yes' : 'No',
                    $item['count'] ?? 0,
                    $item['avg_matching_time_minutes'] ?? '',
                    $item['has_matching_attempts'] ?? 0
                ];

            default:
                return array_values($item);
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
     * Clean up old export files
     */
    public function cleanupOldExports(int $daysOld = 7): int
    {
        $exportPath = 'exports';
        $files = Storage::files($exportPath);
        $deletedCount = 0;
        $cutoffTime = Carbon::now()->subDays($daysOld)->timestamp;

        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);

            if ($lastModified < $cutoffTime) {
                Storage::delete($file);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Get download URL for exported file
     */
    public function getDownloadUrl(string $filePath): string
    {
        return Storage::url($filePath);
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

    /**
     * Export unmatched transactions with priority scoring
     */
    public function exportUnmatchedTransactionsCsv(array $transactions, array $filters = []): string
    {
        $filename = $this->generateFilename('unmatched_transactions', 'csv');
        $filePath = 'exports/' . $filename;

        $rows = [];

        // Add metadata
        $rows[] = "# Decta Unmatched Transactions Report";
        $rows[] = "# Generated: " . Carbon::now()->format('Y-m-d H:i:s');
        if (!empty($filters)) {
            $rows[] = "# Filters: " . json_encode($filters);
        }
        $rows[] = "# Total Records: " . count($transactions);
        $rows[] = "";

        // Headers
        $headers = [
            'Payment ID', 'Transaction Date', 'Amount', 'Currency', 'Merchant Name',
            'Merchant ID', 'Approval ID', 'Return Reference', 'Attempts', 'Priority',
            'Priority Score', 'Last Attempt', 'Error Message'
        ];

        $rows[] = '"' . implode('","', $headers) . '"';

        // Data rows with priority scoring
        foreach ($transactions as $transaction) {
            $priority = $this->calculatePriority($transaction);

            $rowData = [
                $transaction['payment_id'] ?? '',
                $transaction['transaction_date'] ?? '',
                $transaction['amount'] ?? 0,
                $transaction['currency'] ?? '',
                $transaction['merchant_name'] ?? '',
                $transaction['merchant_id'] ?? '',
                $transaction['approval_id'] ?? '',
                $transaction['return_reference'] ?? '',
                is_array($transaction['attempts']) ? count($transaction['attempts']) : 0,
                $priority['level'],
                $priority['score'],
                $this->getLastAttemptDate($transaction['attempts']),
                $this->getLastErrorMessage($transaction['attempts'])
            ];

            $rows[] = '"' . implode('","', array_map(function($value) {
                    return str_replace('"', '""', $value);
                }, $rowData)) . '"';
        }

        Storage::put($filePath, implode("\n", $rows));

        return $filePath;
    }

    /**
     * Calculate priority for unmatched transactions
     */
    private function calculatePriority(array $transaction): array
    {
        $score = 0;
        $level = 'Low';

        // Amount-based scoring
        $amount = $transaction['amount'] ?? 0;
        if ($amount > 1000) {
            $score += 50;
        } elseif ($amount > 100) {
            $score += 30;
        } elseif ($amount > 10) {
            $score += 10;
        }

        // Has approval ID
        if (!empty($transaction['approval_id'])) {
            $score += 25;
        }

        // Has return reference
        if (!empty($transaction['return_reference'])) {
            $score += 20;
        }

        // Recent transaction (last 7 days)
        $transactionDate = Carbon::parse($transaction['transaction_date']);
        if ($transactionDate->diffInDays(Carbon::now()) <= 7) {
            $score += 15;
        }

        // Few attempts (might be easy to match)
        $attempts = is_array($transaction['attempts']) ? count($transaction['attempts']) : 0;
        if ($attempts <= 1) {
            $score += 10;
        }

        // Determine priority level
        if ($score >= 70) {
            $level = 'High';
        } elseif ($score >= 40) {
            $level = 'Medium';
        }

        return [
            'score' => $score,
            'level' => $level
        ];
    }

    /**
     * Get last attempt date
     */
    private function getLastAttemptDate(?array $attempts): string
    {
        if (empty($attempts)) {
            return '';
        }

        $lastAttempt = end($attempts);
        return $lastAttempt['attempted_at'] ?? '';
    }

    /**
     * Get last error message
     */
    private function getLastErrorMessage(?array $attempts): string
    {
        if (empty($attempts)) {
            return '';
        }

        $lastAttempt = end($attempts);
        return $lastAttempt['error_message'] ?? $lastAttempt['result'] ?? '';
    }
}
