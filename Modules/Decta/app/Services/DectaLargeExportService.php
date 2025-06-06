<?php

namespace Modules\Decta\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DectaLargeExportService
{
    /**
     * Export large transaction dataset with streaming and chunking
     */
    public function exportLargeTransactionDataset(array $filters = [], string $format = 'csv'): array
    {
        try {
            $startTime = microtime(true);

            Log::info('Starting large dataset export', [
                'filters' => $filters,
                'format' => $format,
                'estimated_records' => $this->estimateRecordCount($filters)
            ]);

            // FIXED: Normalize format and generate proper filename with date range
            $normalizedFormat = $this->normalizeFormat($format);
            $filename = $this->generateLargeExportFilename('transactions_complete', $normalizedFormat, $filters);
            $filePath = 'exports/' . $filename;
            $fullPath = storage_path('app/' . $filePath);

            // Ensure directory exists
            $this->ensureExportsDirectory();

            if ($normalizedFormat === 'csv') {
                $this->exportLargeDatasetToCsv($fullPath, $filters);
            } else {
                $this->exportLargeDatasetToExcel($fullPath, $filters);
            }

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

            Log::info('Large dataset export completed', [
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'execution_time_seconds' => $executionTime
            ]);

            return [
                'success' => true,
                'file_path' => $filePath,
                'filename' => $filename,
                'file_size' => $fileSize,
                'execution_time' => $executionTime,
                'record_count' => $this->getActualRecordCount($filters)
            ];

        } catch (\Exception $e) {
            Log::error('Large dataset export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export large dataset to CSV using streaming approach
     */
    private function exportLargeDatasetToCsv(string $fullPath, array $filters): void
    {
        $file = fopen($fullPath, 'w');
        if (!$file) {
            throw new \Exception("Cannot open file for writing: {$fullPath}");
        }

        try {
            // Write metadata
            fwrite($file, "# Decta Complete Transaction Export\n");
            fwrite($file, "# Generated: " . Carbon::now()->format('Y-m-d H:i:s') . "\n");
            fwrite($file, "# Date Range: " . $this->getDateRangeString($filters) . "\n");
            fwrite($file, "# Filters: " . json_encode($filters) . "\n");
            fwrite($file, "# \n");

            // Write headers - ALL transaction fields
            $headers = $this->getAllTransactionHeaders();
            fputcsv($file, $headers);

            // Stream data in chunks to avoid memory issues
            $chunkSize = 1000;
            $offset = 0;
            $totalRecords = 0;

            do {
                $chunk = $this->getTransactionChunk($filters, $chunkSize, $offset);

                foreach ($chunk as $transaction) {
                    $row = $this->formatCompleteTransactionRow($transaction);
                    fputcsv($file, $row);
                    $totalRecords++;
                }

                $offset += $chunkSize;

                // Log progress every 10k records
                if ($totalRecords % 10000 === 0) {
                    Log::info("Export progress: {$totalRecords} records processed");
                }

            } while (count($chunk) === $chunkSize);

            Log::info("CSV export completed", ['total_records' => $totalRecords]);

        } finally {
            fclose($file);
        }
    }

    /**
     * Export large dataset to Excel with memory optimization
     */
    private function exportLargeDatasetToExcel(string $fullPath, array $filters): void
    {
        // For very large datasets, use CSV for Excel compatibility
        // Excel has row limits and memory issues with 500k+ records
        $csvPath = str_replace('.xlsx', '.csv', $fullPath);
        $this->exportLargeDatasetToCsv($csvPath, $filters);

        // Rename to keep the .xlsx extension if requested
        if (pathinfo($fullPath, PATHINFO_EXTENSION) === 'xlsx') {
            rename($csvPath, $fullPath);
        }
    }

    /**
     * FIXED: Normalize format to proper file extensions
     */
    private function normalizeFormat(string $format): string
    {
        switch (strtolower($format)) {
            case 'excel':
            case 'xlsx':
                return 'xlsx';
            case 'csv':
            default:
                return 'csv';
        }
    }

    /**
     * Get date range string for metadata
     */
    private function getDateRangeString(array $filters): string
    {
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            return $filters['date_from'] . ' to ' . $filters['date_to'];
        } elseif (!empty($filters['date_from'])) {
            return 'From ' . $filters['date_from'];
        } elseif (!empty($filters['date_to'])) {
            return 'Until ' . $filters['date_to'];
        }
        return 'All dates';
    }

    /**
     * Get all transaction headers - complete field list
     */
    private function getAllTransactionHeaders(): array
    {
        return [
            // Core identifiers
            'ID',
            'Payment ID',
            'Card',
            // Merchant information
            'Merchant Name',
            'Merchant ID',
            'Terminal ID',
            'Card Type Name',
            // Transaction details
            'ACQ Reference Number',
            'Transaction Batch ID',
            'Transaction Date Time',
            'Transaction Type',
            'Transaction Amount',
            'Transaction CCY',
            'MSC',
            'Transaction Return Reference Number',
            'Transaction Approval ID',
            'Transaction Processing Date',
            'Merchant IBAN',
            'Proc Code',
            'Issuer Country',
            'Proc Region',
            'MCC',
            'Merchant Country',
            'Tran Region',
            'Card Product Type',
            'User Define Field 1',
            'User Define Field 2',
            'User Define Field 3',
            'Merchant Legal Name',
            'Card Product Class',
            'ECI/SLI',
            'SCA Exemption',
            'Point Code',
            'Issuer Country',
        ];
    }

    /**
     * Get transaction chunk with all fields - FIXED JOIN ISSUE
     */
    private function getTransactionChunk(array $filters, int $limit, int $offset): array
    {
        $query = DB::table('decta_transactions as dt')
            ->select([
                'dt.id',                        // ID
                'dt.payment_id',                // Payment ID
                'dt.card',                      // Card
                'dt.merchant_name',             // Merchant Name
                'dt.merchant_id',               // Merchant ID
                'dt.terminal_id',               // Terminal ID
                'dt.card_type_name',            // Card Type Name
                'dt.acq_ref_nr',                // ACQ Reference Number
                'dt.tr_batch_id',               // Transaction Batch ID
                'dt.tr_date_time',              // Transaction Date Time
                'dt.tr_type',                   // Transaction Type
                'dt.tr_amount',                 // Transaction Amount
                'dt.tr_ccy',                    // Transaction CCY
                'dt.msc',                       // MSC
                'dt.tr_ret_ref_nr',             // Transaction Return Reference Number
                'dt.tr_approval_id',            // Transaction Approval ID
                'dt.tr_processing_date',        // Transaction Processing Date
                'dt.merchant_iban_code',        // Merchant IBAN
                'dt.proc_code',                 // Proc Code
                'dt.issuer_country',            // Issuer Country
                'dt.proc_region',               // Proc Region
                'dt.mcc',                       // MCC
                'dt.merchant_country',          // Merchant Country
                'dt.tran_region',               // Tran Region
                'dt.card_product_type',         // Card Product Type
                'dt.user_define_field1',        // User Define Field 1
                'dt.user_define_field2',        // User Define Field 2
                'dt.user_define_field3',        // User Define Field 3
                'dt.merchant_legal_name',       // Merchant Legal Name
                'dt.card_product_class',        // Card Product Class
                'dt.eci_sli',                   // ECI/SLI
                'dt.sca_exemption',             // SCA Exemption
                'dt.point_code',                // Point Code
                'dt.issuer_country as issuer_country_duplicate', // Issuer Country (duplicate)
            ]);

        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->where('dt.tr_date_time', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('dt.tr_date_time', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['merchant_id'])) {
            $query->where('dt.merchant_id', $filters['merchant_id']);
        }

        if (!empty($filters['currency'])) {
            $query->where('dt.tr_ccy', $filters['currency']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'matched') {
                $query->where('dt.is_matched', true);
            } elseif ($filters['status'] === 'unmatched') {
                $query->where('dt.is_matched', false);
            } else {
                $query->where('dt.status', $filters['status']);
            }
        }

        return $query->orderBy('dt.tr_date_time', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Format complete transaction row for export
     */
    private function formatCompleteTransactionRow($transaction): array
    {
        return [
            $transaction->id,                                           // ID
            $transaction->payment_id,                                   // Payment ID
            $transaction->card,                                         // Card
            $transaction->merchant_name,                                // Merchant Name
            $transaction->merchant_id,                                  // Merchant ID
            $transaction->terminal_id,                                  // Terminal ID
            $transaction->card_type_name,                               // Card Type Name
            $transaction->acq_ref_nr,                                   // ACQ Reference Number
            $transaction->tr_batch_id,                                  // Transaction Batch ID
            $transaction->tr_date_time,                                 // Transaction Date Time
            $transaction->tr_type,                                      // Transaction Type
            $transaction->tr_amount ? $transaction->tr_amount / 100 : 0, // Transaction Amount (convert from cents)
            $transaction->tr_ccy,                                       // Transaction CCY
            $transaction->msc,                                          // MSC
            $transaction->tr_ret_ref_nr,                                // Transaction Return Reference Number
            $transaction->tr_approval_id,                               // Transaction Approval ID
            $transaction->tr_processing_date,                           // Transaction Processing Date
            $transaction->merchant_iban_code,                           // Merchant IBAN
            $transaction->proc_code,                                    // Proc Code
            $transaction->issuer_country,                               // Issuer Country
            $transaction->proc_region,                                  // Proc Region
            $transaction->mcc,                                          // MCC
            $transaction->merchant_country,                             // Merchant Country
            $transaction->tran_region,                                  // Tran Region
            $transaction->card_product_type,                            // Card Product Type
            $transaction->user_define_field1,                           // User Define Field 1
            $transaction->user_define_field2,                           // User Define Field 2
            $transaction->user_define_field3,                           // User Define Field 3
            $transaction->merchant_legal_name,                          // Merchant Legal Name
            $transaction->card_product_class,                           // Card Product Class
            $transaction->eci_sli,                                      // ECI/SLI
            $transaction->sca_exemption,                                // SCA Exemption
            $transaction->point_code,                                   // Point Code
            $transaction->issuer_country,                               // Issuer Country (duplicate)
        ];
    }

    /**
     * Estimate record count for progress tracking
     */
    private function estimateRecordCount(array $filters): int
    {
        $query = DB::table('decta_transactions');

        if (!empty($filters['date_from'])) {
            $query->where('tr_date_time', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('tr_date_time', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['merchant_id'])) {
            $query->where('merchant_id', $filters['merchant_id']);
        }

        if (!empty($filters['currency'])) {
            $query->where('tr_ccy', $filters['currency']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'matched') {
                $query->where('is_matched', true);
            } elseif ($filters['status'] === 'unmatched') {
                $query->where('is_matched', false);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        return $query->count();
    }

    /**
     * Get actual record count after export
     */
    private function getActualRecordCount(array $filters): int
    {
        return $this->estimateRecordCount($filters);
    }

    /**
     * FIXED: Generate filename with date range and proper extensions
     */
    private function generateLargeExportFilename(string $type, string $extension, array $filters = []): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $baseName = "decta_{$type}";

        // Add date range to filename if available
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $dateRange = $filters['date_from'] . '_to_' . $filters['date_to'];
            $baseName .= "_{$dateRange}";
        } elseif (!empty($filters['date_from'])) {
            $baseName .= "_from_{$filters['date_from']}";
        } elseif (!empty($filters['date_to'])) {
            $baseName .= "_until_{$filters['date_to']}";
        }

        return "{$baseName}_{$timestamp}.{$extension}";
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
}
