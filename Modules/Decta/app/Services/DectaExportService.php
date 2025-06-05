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

            Log::info('Starting CSV export with direct file writing', [
                'filename' => $filename,
                'file_path' => $filePath,
                'full_path' => $fullPath,
                'data_count' => count($data),
                'report_type' => $reportType
            ]);

            // Ensure exports directory exists using native PHP
            $exportsDir = storage_path('app/exports');
            if (!is_dir($exportsDir)) {
                if (!mkdir($exportsDir, 0755, true)) {
                    throw new \Exception("Failed to create exports directory: {$exportsDir}");
                }
                Log::info('Created exports directory', ['path' => $exportsDir]);
            }

            // Create CSV content
            $csvContent = $this->generateCsvContent($data, $reportType, $filters);

            Log::info('CSV content generated', [
                'content_length' => strlen($csvContent),
                'content_preview' => substr($csvContent, 0, 200)
            ]);

            // Write file directly using PHP instead of Laravel Storage
            $bytesWritten = file_put_contents($fullPath, $csvContent);

            if ($bytesWritten === false) {
                throw new \Exception("Failed to write CSV file to: {$fullPath}");
            }

            Log::info('File written with file_put_contents', [
                'bytes_written' => $bytesWritten,
                'full_path' => $fullPath
            ]);

            // Verify file exists and has content
            if (!file_exists($fullPath)) {
                throw new \Exception("CSV file was not created successfully at: {$fullPath}");
            }

            $actualFileSize = filesize($fullPath);
            if ($actualFileSize === 0) {
                throw new \Exception("CSV file was created but is empty at: {$fullPath}");
            }

            Log::info('CSV export completed successfully', [
                'file_path' => $filePath,
                'full_path' => $fullPath,
                'file_size' => $actualFileSize,
                'bytes_written' => $bytesWritten
            ]);

            return $filePath;

        } catch (\Exception $e) {
            Log::error('CSV export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'report_type' => $reportType,
                'data_count' => count($data),
                'full_path' => isset($fullPath) ? $fullPath : 'not set'
            ]);
            throw $e;
        }
    }

    /**
     * Export data to Excel format
     */
    public function exportToExcel(array $data, string $reportType, array $filters = []): string
    {
        try {
            $filename = $this->generateFilename($reportType, 'xlsx');
            $filePath = 'exports/' . $filename;
            $fullPath = storage_path('app/' . $filePath);

            Log::info('Starting Excel export', [
                'filename' => $filename,
                'file_path' => $filePath,
                'full_path' => $fullPath,
                'data_count' => count($data),
                'report_type' => $reportType
            ]);

            // Ensure exports directory exists using native PHP
            $exportsDir = storage_path('app/exports');
            if (!is_dir($exportsDir)) {
                if (!mkdir($exportsDir, 0755, true)) {
                    throw new \Exception("Failed to create exports directory: {$exportsDir}");
                }
                Log::info('Created exports directory', ['path' => $exportsDir]);
            }

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

            // Save file directly
            $writer = new Xlsx($spreadsheet);
            $writer->save($fullPath);

            // Verify file exists
            if (!file_exists($fullPath)) {
                throw new \Exception("Excel file was not created successfully at: {$fullPath}");
            }

            $fileSize = filesize($fullPath);
            if ($fileSize === 0) {
                throw new \Exception("Excel file was created but is empty at: {$fullPath}");
            }

            Log::info('Excel export completed', [
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'full_path' => $fullPath
            ]);

            return $filePath;

        } catch (\Exception $e) {
            Log::error('Excel export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'report_type' => $reportType,
                'data_count' => count($data),
                'full_path' => isset($fullPath) ? $fullPath : 'not set'
            ]);
            throw $e;
        }
    }    /**
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
        try {
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
                        return str_replace('"', '""', (string)$value); // Ensure string and escape quotes
                    }, $rowData)) . '"';
            }

            $content = implode("\n", $rows);

            Log::info('CSV content generated', [
                'content_length' => strlen($content),
                'row_count' => count($rows),
                'data_rows' => count($data)
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
            case 'transactions':
                return [
                    'Payment ID', 'Transaction Date', 'Amount', 'Currency', 'Merchant Name',
                    'Merchant ID', 'Terminal ID', 'Card Type', 'Transaction Type', 'Status',
                    'Is Matched', 'Matched At', 'Gateway Transaction ID', 'Error Message'
                ];

            case 'scheme':
                return [
                    'Card Type', 'Transaction Type', 'Currency', 'Amount', 'Transaction Count',
                    'Fee', 'Merchant Legal Name'
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
                Log::warning('Unknown report type for headers', ['report_type' => $reportType]);
                return ['Data'];
        }
    }
    /**
     * Format row data for CSV export
     */
    private function formatRowForCsv(array $item, string $reportType): array
    {
        try {
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
                        isset($item['is_matched']) && $item['is_matched'] ? 'Yes' : 'No',
                        $item['matched_at'] ?? '',
                        $item['gateway_info']['transaction_id'] ?? '',
                        $item['error_message'] ?? ''
                    ];

                case 'scheme':
                    return [
                        $item['card_type'] ?? '',
                        $item['transaction_type'] ?? '',
                        $item['currency'] ?? '',
                        $item['amount'] ?? 0,
                        $item['count'] ?? 0,
                        $item['fee'] ?? 0,
                        $item['merchant_legal_name'] ?? ''
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
                        isset($item['is_matched']) && $item['is_matched'] ? 'Yes' : 'No',
                        $item['count'] ?? 0,
                        $item['avg_matching_time_minutes'] ?? '',
                        $item['has_matching_attempts'] ?? 0
                    ];

                default:
                    return array_values($item);
            }
        } catch (\Exception $e) {
            Log::error('Row formatting failed', [
                'error' => $e->getMessage(),
                'report_type' => $reportType,
                'item' => $item
            ]);
            // Return safe default
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
    /**
     * Test export functionality - DEBUGGING METHOD
     */
    public function testExport(): array
    {
        try {
            $testData = [
                [
                    'card_type' => 'VISA',
                    'transaction_type' => '05',
                    'currency' => 'EUR',
                    'amount' => 100.50,
                    'count' => 5,
                    'fee' => 2.50,
                    'merchant_legal_name' => 'Test Merchant Ltd'
                ],
                [
                    'card_type' => 'MC',
                    'transaction_type' => '06',
                    'currency' => 'USD',
                    'amount' => 75.25,
                    'count' => 3,
                    'fee' => 1.88,
                    'merchant_legal_name' => 'Another Merchant Inc'
                ]
            ];

            $filePath = $this->exportToCsv($testData, 'scheme', ['test' => true]);
            $fullPath = storage_path('app/' . $filePath);

            return [
                'success' => true,
                'file_path' => $filePath,
                'full_path' => $fullPath,
                'file_exists' => file_exists($fullPath),
                'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                'file_content' => file_exists($fullPath) ? substr(file_get_contents($fullPath), 0, 500) : null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
}
