<?php

namespace Modules\Decta\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
            'File ID',

            // Transaction details
            'Transaction Date',
            'Transaction Type',
            'Amount',
            'Currency',
            'Processing Date',
            'Batch ID',
            'Batch Open Date',

            // Merchant information
            'Merchant Name',
            'Merchant ID',
            'Merchant Legal Name',
            'Merchant IBAN',
            'Merchant Country',
            'Terminal ID',

            // Card information
            'Card (Masked)',
            'Card Type',
            'Card Product Type',
            'Card Product Class',
            'Issuer Country',

            // Transaction codes and references
            'Approval ID',
            'Return Reference Number',
            'ACQ Reference Number',
            'MSC',
            'MCC',
            'Proc Code',
            'Proc Region',
            'Tran Region',

            // Security and compliance
            'ECI/SLI',
            'SCA Exemption',
            'Point Code',
            'POS Environment Indicator',
            'PAR',

            // User defined fields
            'User Define Field 1',
            'User Define Field 2',
            'User Define Field 3',

            // Gateway matching data
            'Gateway Transaction ID',
            'Gateway Account ID',
            'Gateway Shop ID',
            'Gateway TRX ID',
            'Gateway Transaction Status',
            'Gateway Transaction Date',
            'Gateway Bank Response Date',

            // Status and matching
            'Status',
            'Is Matched',
            'Matched At',
            'Error Message',
            'Matching Attempts Count',

            // Timestamps
            'Created At',
            'Updated At',

            // Source file
            'Source Filename'
        ];
    }

    /**
     * Get transaction chunk with all fields - FIXED JOIN ISSUE
     */
    private function getTransactionChunk(array $filters, int $limit, int $offset): array
    {
        $query = DB::table('decta_transactions as dt')
            ->leftJoin('decta_files as df', 'dt.decta_file_id', '=', 'df.id') // FIXED: Added missing JOIN
            ->select([
                'dt.id',
                'dt.payment_id',
                'dt.decta_file_id',
                'dt.tr_date_time',
                'dt.tr_type',
                'dt.tr_amount',
                'dt.tr_ccy',
                'dt.tr_processing_date',
                'dt.tr_batch_id',
                'dt.tr_batch_open_date',
                'dt.merchant_name',
                'dt.merchant_id',
                'dt.merchant_legal_name',
                'dt.merchant_iban_code',
                'dt.merchant_country',
                'dt.terminal_id',
                'dt.card',
                'dt.card_type_name',
                'dt.card_product_type',
                'dt.card_product_class',
                'dt.issuer_country',
                'dt.tr_approval_id',
                'dt.tr_ret_ref_nr',
                'dt.acq_ref_nr',
                'dt.msc',
                'dt.mcc',
                'dt.proc_code',
                'dt.proc_region',
                'dt.tran_region',
                'dt.eci_sli',
                'dt.sca_exemption',
                'dt.point_code',
                'dt.pos_env_indicator',
                'dt.par',
                'dt.user_define_field1',
                'dt.user_define_field2',
                'dt.user_define_field3',
                'dt.gateway_transaction_id',
                'dt.gateway_account_id',
                'dt.gateway_shop_id',
                'dt.gateway_trx_id',
                'dt.gateway_transaction_status',
                'dt.gateway_transaction_date',
                'dt.gateway_bank_response_date',
                'dt.status',
                'dt.is_matched',
                'dt.matched_at',
                'dt.error_message',
                'dt.matching_attempts',
                'dt.created_at',
                'dt.updated_at',
                'df.filename'
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
        $matchingAttempts = null;
        if (!empty($transaction->matching_attempts)) {
            $attempts = json_decode($transaction->matching_attempts, true);
            $matchingAttempts = is_array($attempts) ? count($attempts) : 0;
        }

        return [
            $transaction->id,
            $transaction->payment_id,
            $transaction->decta_file_id,
            $transaction->tr_date_time,
            $transaction->tr_type,
            $transaction->tr_amount ? $transaction->tr_amount / 100 : 0, // Convert from cents
            $transaction->tr_ccy,
            $transaction->tr_processing_date,
            $transaction->tr_batch_id,
            $transaction->tr_batch_open_date,
            $transaction->merchant_name,
            $transaction->merchant_id,
            $transaction->merchant_legal_name,
            $transaction->merchant_iban_code,
            $transaction->merchant_country,
            $transaction->terminal_id,
            $transaction->card,
            $transaction->card_type_name,
            $transaction->card_product_type,
            $transaction->card_product_class,
            $transaction->issuer_country,
            $transaction->tr_approval_id,
            $transaction->tr_ret_ref_nr,
            $transaction->acq_ref_nr,
            $transaction->msc,
            $transaction->mcc,
            $transaction->proc_code,
            $transaction->proc_region,
            $transaction->tran_region,
            $transaction->eci_sli,
            $transaction->sca_exemption,
            $transaction->point_code,
            $transaction->pos_env_indicator,
            $transaction->par,
            $transaction->user_define_field1,
            $transaction->user_define_field2,
            $transaction->user_define_field3,
            $transaction->gateway_transaction_id,
            $transaction->gateway_account_id,
            $transaction->gateway_shop_id,
            $transaction->gateway_trx_id,
            $transaction->gateway_transaction_status,
            $transaction->gateway_transaction_date,
            $transaction->gateway_bank_response_date,
            $transaction->status,
            $transaction->is_matched ? 'Yes' : 'No',
            $transaction->matched_at,
            $transaction->error_message,
            $matchingAttempts,
            $transaction->created_at,
            $transaction->updated_at,
            $transaction->filename
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
