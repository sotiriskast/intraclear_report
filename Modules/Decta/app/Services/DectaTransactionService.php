<?php

namespace Modules\Decta\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Decta\Models\DectaFile;
use Modules\Decta\Models\DectaTransaction;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DectaTransactionService
{
    /**
     * CSV column mapping for Decta transaction file
     */
    private const CSV_COLUMNS = [
        'PAYMENT_ID' => 'payment_id',
        'CARD' => 'card',
        'MERCHANT_NAME' => 'merchant_name',
        'MERCHANT_ID' => 'merchant_id',
        'TERMINAL_ID' => 'terminal_id',
        'CARD_TYPE_NAME' => 'card_type_name',
        'ACQ_REF_NR' => 'acq_ref_nr',
        'TR_BATCH_ID' => 'tr_batch_id',
        'TR_BATCH_OPEN_DATE' => 'tr_batch_open_date',
        'TR_DATE_TIME' => 'tr_date_time',
        'TR_TYPE' => 'tr_type',
        'TR_AMOUNT' => 'tr_amount',
        'TR_CCY' => 'tr_ccy',
        'MSC' => 'msc',
        'TR_RET_REF_NR' => 'tr_ret_ref_nr',
        'TR_APPROVAL_ID' => 'tr_approval_id',
        'TR_PROCESSING_DATE' => 'tr_processing_date',
        'MERCHANT_IBAN_CODE' => 'merchant_iban_code',
        'PROC_CODE' => 'proc_code',
        'ISSUER_COUNTRY' => 'issuer_country',
        'PROC_REGION' => 'proc_region',
        'MCC' => 'mcc',
        'MERCHANT_COUNTRY' => 'merchant_country',
        'TRAN_REGION' => 'tran_region',
        'CARD_PRODUCT_TYPE' => 'card_product_type',
        'USER_DEFINE_FIELD1' => 'user_define_field1',
        'USER_DEFINE_FIELD2' => 'user_define_field2',
        'USER_DEFINE_FIELD3' => 'user_define_field3',
        'MERCHANT_LEGAL_NAME' => 'merchant_legal_name',
        'CARD_PRODUCT_CLASS' => 'card_product_class',
        'ECI_SLI' => 'eci_sli',
        'SCA_EXEMPTION' => 'sca_exemption',
        'POINT_CODE' => 'point_code',
        'POS_ENV_INDICATOR' => 'pos_env_indicator',
        'PAR' => 'par',
    ];

    /**
     * Process CSV file and store transactions
     *
     * @param DectaFile $file
     * @param string $content
     * @return array Processing results
     */
    public function processCsvFile(DectaFile $file, string $content): array
    {
        $results = [
            'total_rows' => 0,
            'processed' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        try {
            Log::info('Starting CSV processing', [
                'file_id' => $file->id,
                'filename' => $file->filename,
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 500),
            ]);

            // Parse CSV content - try different approaches
            $lines = $this->parseContentToLines($content);

            if (empty($lines)) {
                throw new Exception('CSV file appears to be empty or unreadable');
            }

            Log::info('CSV content parsed', [
                'file_id' => $file->id,
                'total_lines' => count($lines),
                'first_line' => $lines[0] ?? 'N/A',
            ]);

            // Get headers from first line
            $headerLine = array_shift($lines);
            $headers = $this->parseCsvLine($headerLine);

            if (empty($headers)) {
                throw new Exception('No headers found in CSV file');
            }

            // Normalize headers (remove BOM, trim whitespace, etc.)
            $headers = $this->normalizeHeaders($headers);

            Log::info('CSV headers parsed', [
                'file_id' => $file->id,
                'header_count' => count($headers),
                'headers' => $headers,
            ]);

            $results['total_rows'] = count($lines);

            if ($results['total_rows'] === 0) {
                throw new Exception('CSV file contains headers but no data rows');
            }

            // Process each data row
            foreach ($lines as $index => $line) {
                if (empty(trim($line))) {
                    continue; // Skip empty lines
                }

                try {
                    $data = $this->parseCsvLine($line);

                    Log::debug('Processing CSV row', [
                        'file_id' => $file->id,
                        'row' => $index + 2,
                        'data_count' => count($data),
                        'header_count' => count($headers),
                        'raw_line' => $line,
                        'parsed_data' => $data,
                    ]);

                    if (count($data) !== count($headers)) {
                        // Try to handle rows with different column counts
                        if (count($data) < count($headers)) {
                            // Pad with empty values
                            $data = array_pad($data, count($headers), '');
                        } else {
                            // Trim extra values
                            $data = array_slice($data, 0, count($headers));
                        }

                        Log::warning('Column count mismatch adjusted', [
                            'file_id' => $file->id,
                            'row' => $index + 2,
                            'original_count' => count($data),
                            'expected_count' => count($headers),
                        ]);
                    }

                    // Combine headers with data
                    $rowData = array_combine($headers, $data);

                    if ($rowData === false) {
                        throw new Exception("Failed to combine headers with data");
                    }

                    // Process and store the transaction
                    $transaction = $this->storeTransaction($file, $rowData);
                    $results['processed']++;

                    Log::debug('Transaction stored', [
                        'file_id' => $file->id,
                        'row' => $index + 2,
                        'transaction_id' => $transaction->id,
                        'payment_id' => $transaction->payment_id,
                    ]);

                } catch (Exception $e) {
                    $results['failed']++;
                    $error = "Row " . ($index + 2) . ": " . $e->getMessage();
                    $results['errors'][] = $error;

                    Log::warning('Failed to process CSV row', [
                        'file_id' => $file->id,
                        'row' => $index + 2,
                        'error' => $e->getMessage(),
                        'raw_line' => $line ?? 'empty',
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            Log::info('Completed processing Decta CSV file', [
                'file_id' => $file->id,
                'results' => $results,
            ]);

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('Failed to process Decta CSV file', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $results;
    }

    /**
     * Parse content into lines, handling different line endings
     *
     * @param string $content
     * @return array
     */
    private function parseContentToLines(string $content): array
    {
        // Remove BOM if present
        $content = $this->removeBOM($content);

        // Handle different line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Split into lines and filter out empty lines
        $lines = explode("\n", $content);
        $lines = array_filter($lines, fn($line) => !empty(trim($line)));

        return array_values($lines); // Re-index the array
    }

    /**
     * Remove BOM (Byte Order Mark) from content
     *
     * @param string $content
     * @return string
     */
    private function removeBOM(string $content): string
    {
        // Remove UTF-8 BOM
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        return $content;
    }

    /**
     * Normalize headers by trimming whitespace and removing special characters
     *
     * @param array $headers
     * @return array
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header) {
            // Remove BOM, trim whitespace, convert to uppercase
            $header = trim($header);
            $header = str_replace(["\xEF\xBB\xBF", "\r", "\n"], '', $header);
            return strtoupper($header);
        }, $headers);
    }

    /**
     * Store individual transaction record
     *
     * @param DectaFile $file
     * @param array $rowData
     * @return DectaTransaction
     */
    private function storeTransaction(DectaFile $file, array $rowData): DectaTransaction
    {
        // Map CSV data to database fields
        $transactionData = [
            'decta_file_id' => $file->id,
        ];
        foreach (self::CSV_COLUMNS as $csvColumn => $dbColumn) {
            $value = $rowData[$csvColumn] ?? null;

            // Handle special data types
            switch ($dbColumn) {
                case 'tr_amount':
                    // Convert amount to cents (multiply by 100)
                    if ($value !== null && $value !== '') {
                        $cleanValue = str_replace([',', ' '], '', $value);
                        $transactionData[$dbColumn] = (int)round(floatval($cleanValue) * 100);
                    } else {
                        $transactionData[$dbColumn] = null;
                    }
                    break;

                case 'tr_date_time':
                case 'tr_batch_open_date':
                case 'tr_processing_date':
                    // Parse dates
                    $transactionData[$dbColumn] = $this->parseDate($value);
                    break;

                default:
                    $transactionData[$dbColumn] = !empty($value) ? $value : null;
                    break;
            }
        }

        return DectaTransaction::create($transactionData);
    }

    /**
     * Enhanced CSV processing with improved resume capability
     *
     * @param DectaFile $file
     * @param string $content
     * @param int $alreadyProcessed Number of transactions already in database
     * @return array Processing results
     */
    public function processCsvFileWithResume(DectaFile $file, string $content, int $alreadyProcessed = 0): array
    {
        $results = [
            'total_rows' => 0,
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'resumed_from_row' => $alreadyProcessed + 1, // +1 because we skip header
            'errors' => [],
        ];

        try {
            Log::info('Starting CSV processing with enhanced resume capability', [
                'file_id' => $file->id,
                'filename' => $file->filename,
                'content_length' => strlen($content),
                'already_processed' => $alreadyProcessed,
            ]);

            // Parse CSV content
            $lines = $this->parseContentToLines($content);

            if (empty($lines)) {
                throw new Exception('CSV file appears to be empty or unreadable');
            }

            // Get headers from first line
            $headerLine = array_shift($lines); // Remove header from lines array
            $headers = $this->parseCsvLine($headerLine);
            $headers = $this->normalizeHeaders($headers);

            if (empty($headers)) {
                throw new Exception('No headers found in CSV file');
            }

            // Now $lines contains only data rows (no header)
            $results['total_rows'] = count($lines);

            Log::info('CSV structure analyzed', [
                'file_id' => $file->id,
                'total_data_rows' => $results['total_rows'],
                'already_processed' => $alreadyProcessed,
                'headers' => $headers,
            ]);

            if ($results['total_rows'] === 0) {
                throw new Exception('CSV file contains headers but no data rows');
            }

            // Validate that we're not trying to skip more rows than exist
            if ($alreadyProcessed > $results['total_rows']) {
                Log::warning('Already processed count exceeds total rows, starting fresh', [
                    'already_processed' => $alreadyProcessed,
                    'total_rows' => $results['total_rows'],
                ]);
                $alreadyProcessed = 0;
            }

            // Get existing payment IDs to avoid duplicates
            $existingPaymentIds = $this->getExistingPaymentIds($file);
            Log::info('Found existing payment IDs', [
                'count' => count($existingPaymentIds),
                'sample' => array_slice($existingPaymentIds, 0, 5),
            ]);

            // Process rows starting from where we left off
            $startFromRow = $alreadyProcessed;
            $processedInThisRun = 0;

            for ($rowIndex = 0; $rowIndex < count($lines); $rowIndex++) {
                $csvRowNumber = $rowIndex + 2; // +2 because: +1 for 1-based counting, +1 for header row
                $line = $lines[$rowIndex];

                if (empty(trim($line))) {
                    continue; // Skip empty lines
                }

                // Skip rows that were already processed
                if ($rowIndex < $startFromRow) {
                    $results['skipped']++;
                    continue;
                }

                try {
                    $data = $this->parseCsvLine($line);

                    Log::debug('Processing CSV row for resume', [
                        'file_id' => $file->id,
                        'csv_row_number' => $csvRowNumber,
                        'array_index' => $rowIndex,
                        'data_count' => count($data),
                        'header_count' => count($headers),
                        'raw_line' => substr($line, 0, 200), // Truncate for logging
                    ]);

                    // Handle column count mismatch
                    if (count($data) !== count($headers)) {
                        if (count($data) < count($headers)) {
                            $data = array_pad($data, count($headers), '');
                            Log::debug('Padded row with empty values', [
                                'csv_row_number' => $csvRowNumber,
                                'original_count' => count($data),
                                'padded_to' => count($headers),
                            ]);
                        } else {
                            $data = array_slice($data, 0, count($headers));
                            Log::debug('Trimmed excess columns', [
                                'csv_row_number' => $csvRowNumber,
                                'original_count' => count($data),
                                'trimmed_to' => count($headers),
                            ]);
                        }
                    }

                    // Combine headers with data
                    $rowData = array_combine($headers, $data);
                    if ($rowData === false) {
                        throw new Exception("Failed to combine headers with data");
                    }

                    // Check for duplicates based on payment_id
                    $paymentId = $rowData['PAYMENT_ID'] ?? null;
                    if (empty($paymentId)) {
                        throw new Exception("Missing PAYMENT_ID in row data");
                    }

                    // Skip if this payment ID was already processed
                    if (in_array($paymentId, $existingPaymentIds)) {
                        Log::debug('Skipping duplicate payment ID', [
                            'payment_id' => $paymentId,
                            'csv_row_number' => $csvRowNumber,
                        ]);
                        $results['skipped']++;
                        continue;
                    }

                    // Store the transaction
                    $transaction = $this->storeTransaction($file, $rowData);
                    $results['processed']++;
                    $processedInThisRun++;

                    // Add to existing payment IDs to prevent duplicates within this run
                    $existingPaymentIds[] = $paymentId;

                    Log::debug('Transaction stored successfully', [
                        'file_id' => $file->id,
                        'csv_row_number' => $csvRowNumber,
                        'transaction_id' => $transaction->id,
                        'payment_id' => $transaction->payment_id,
                        'processed_in_this_run' => $processedInThisRun,
                    ]);

                } catch (Exception $e) {
                    $results['failed']++;
                    $error = "Row {$csvRowNumber}: " . $e->getMessage();
                    $results['errors'][] = $error;

                    Log::warning('Failed to process CSV row during resume', [
                        'file_id' => $file->id,
                        'csv_row_number' => $csvRowNumber,
                        'array_index' => $rowIndex,
                        'error' => $e->getMessage(),
                        'raw_line' => substr($line ?? '', 0, 200),
                        'payment_id' => $rowData['PAYMENT_ID'] ?? 'unknown',
                    ]);
                }
            }

            Log::info('Completed CSV processing with resume', [
                'file_id' => $file->id,
                'results' => $results,
                'processed_in_this_run' => $processedInThisRun,
            ]);

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('Failed to process CSV file with resume', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $results;
    }
    /**
     * Improved method to count CSV rows more accurately
     *
     * @param string $content
     * @return int
     */
    public function countCsvRows(string $content): int
    {
        $lines = $this->parseContentToLines($content);

        if (empty($lines)) {
            return 0;
        }

        // Subtract 1 for header row
        return max(0, count($lines) - 1);
    }

    /**
     * Get detailed file processing progress with better accuracy
     *
     * @param DectaFile $file
     * @return array
     */
    public function getFileProgress(DectaFile $file): array
    {
        try {
            // Get file content
            $content = Storage::disk('decta')->get($file->local_path);
            if (!$content) {
                return [
                    'total_rows' => 0,
                    'processed_rows' => 0,
                    'completion_percentage' => 0,
                    'can_resume' => false,
                    'status' => 'file_not_found',
                ];
            }

            $totalRows = $this->countCsvRows($content);
            $processedRows = $file->dectaTransactions()->count();
            $completionPercentage = $totalRows > 0 ? ($processedRows / $totalRows) * 100 : 0;

            // Check for potential issues
            $issues = [];
            if ($processedRows > $totalRows) {
                $issues[] = 'More transactions in database than CSV rows - possible duplicate processing';
            }

            // Get sample of processed payment IDs for verification
            $sampleProcessed = $file->dectaTransactions()
                ->orderBy('id')
                ->limit(5)
                ->pluck('payment_id')
                ->toArray();

            return [
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
                'remaining_rows' => max(0, $totalRows - $processedRows),
                'completion_percentage' => round($completionPercentage, 2),
                'can_resume' => $processedRows < $totalRows && $processedRows > 0,
                'is_complete' => $processedRows >= $totalRows,
                'status' => $this->getProgressStatus($processedRows, $totalRows),
                'sample_processed_payment_ids' => $sampleProcessed,
                'issues' => $issues,
            ];

        } catch (Exception $e) {
            Log::error('Error getting file progress', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'total_rows' => 0,
                'processed_rows' => 0,
                'completion_percentage' => 0,
                'can_resume' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get progress status based on processed vs total rows
     *
     * @param int $processed
     * @param int $total
     * @return string
     */
    private function getProgressStatus(int $processed, int $total): string
    {
        if ($total === 0) {
            return 'empty_file';
        }

        if ($processed === 0) {
            return 'not_started';
        }

        if ($processed >= $total) {
            return 'complete';
        }

        if ($processed > 0) {
            return 'in_progress';
        }

        return 'unknown';
    }
    /**
     * Parse CSV line handling quoted values and different delimiters
     *
     * @param string $line
     * @return array
     */
    private function parseCsvLine(string $line): array
    {
        // Try semicolon delimiter first (common in European CSV files)
        if (strpos($line, ';') !== false) {
            return str_getcsv($line, ';');
        }

        // Fall back to comma delimiter
        return str_getcsv($line, ',');
    }

    /**
     * Parse date from various formats
     *
     * @param string|null $dateString
     * @return Carbon|null
     */
    /**
     * Parse date from various formats by first detecting the format
     *
     * @param string|null $dateString
     * @return Carbon|null
     */
    private function parseDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        // Clean the date string
        $dateString = trim($dateString);

        try {
            // First, try to detect the format
            $detectedFormat = $this->detectDateFormat($dateString);

            if ($detectedFormat) {
                try {
                    $date = Carbon::createFromFormat($detectedFormat, $dateString);
                    if ($date && $date->format($detectedFormat) === $dateString) {
                        return $date;
                    }
                } catch (Exception $e) {
                    Log::debug('Failed to parse with detected format', [
                        'date_string' => $dateString,
                        'detected_format' => $detectedFormat,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // If format detection fails, try common formats one by one
            $formats = [
                'Y-m-d H:i:s',
                'Y-m-d',
                'd/m/Y H:i:s',
                'd/m/Y',
                'm/d/Y H:i:s',
                'm/d/Y',
                'Y-m-d\TH:i:s',
                'Y-m-d\TH:i:s\Z',
                'd.m.Y H:i:s',
                'd.m.Y',
                'Y.m.d H:i:s',  // Fixed: was 'YYYY.mm.dd H:i:s'
                'Y.m.d',        // Fixed: was 'YYYY.mm.dd'
                'Y/m/d H:i:s',
                'Y/m/d',
                'j/n/Y H:i:s',  // Single digit day/month
                'j/n/Y',
                'n/j/Y H:i:s',  // US format with single digits
                'n/j/Y',
                'j.n.Y H:i:s',
                'j.n.Y',
                'd-m-Y H:i:s',
                'd-m-Y',
                'm-d-Y H:i:s',
                'm-d-Y',
            ];

            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $dateString);

                    // Verify the parsing was successful by formatting back
                    if ($date && $this->validateParsedDate($date, $dateString, $format)) {
                        return $date;
                    }
                } catch (Exception $e) {
                    // Continue to next format
                    continue;
                }
            }

            // Last resort: try Carbon's built-in parsing
            try {
                $date = Carbon::parse($dateString);
                if ($date) {
                    return $date;
                }
            } catch (Exception $e) {
                // Continue to logging
            }

            // If all else fails, log and return null
            Log::warning('Failed to parse date with all methods', [
                'date_string' => $dateString,
                'detected_format' => $detectedFormat ?? 'none',
            ]);

            return null;

        } catch (Exception $e) {
            Log::error('Unexpected error in date parsing', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Detect the most likely date format based on string patterns
     *
     * @param string $dateString
     * @return string|null
     */
    private function detectDateFormat(string $dateString): ?string
    {
        // Define regex patterns with their corresponding Carbon formats
        $patterns = [
            // ISO format variations
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/' => 'Y-m-d H:i:s',
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?$/' => 'Y-m-d\TH:i:s',
            '/^\d{4}-\d{2}-\d{2}$/' => 'Y-m-d',

            // European format (dd/mm/yyyy or dd.mm.yyyy)
            '/^\d{1,2}\/\d{1,2}\/\d{4} \d{2}:\d{2}:\d{2}$/' => 'd/m/Y H:i:s',
            '/^\d{1,2}\/\d{1,2}\/\d{4}$/' => 'd/m/Y',
            '/^\d{1,2}\.\d{1,2}\.\d{4} \d{2}:\d{2}:\d{2}$/' => 'd.m.Y H:i:s',
            '/^\d{1,2}\.\d{1,2}\.\d{4}$/' => 'd.m.Y',

            // US format (mm/dd/yyyy)
            '/^\d{1,2}\/\d{1,2}\/\d{4} \d{2}:\d{2}:\d{2}$/' => 'm/d/Y H:i:s',
            '/^\d{1,2}\/\d{1,2}\/\d{4}$/' => 'm/d/Y',

            // Alternative formats
            '/^\d{4}\.\d{1,2}\.\d{1,2} \d{2}:\d{2}:\d{2}$/' => 'Y.m.d H:i:s',
            '/^\d{4}\.\d{1,2}\.\d{1,2}$/' => 'Y.m.d',
            '/^\d{4}\/\d{1,2}\/\d{1,2} \d{2}:\d{2}:\d{2}$/' => 'Y/m/d H:i:s',
            '/^\d{4}\/\d{1,2}\/\d{1,2}$/' => 'Y/m/d',

            // With single digit tolerance
            '/^\d{1,2}-\d{1,2}-\d{4} \d{2}:\d{2}:\d{2}$/' => 'd-m-Y H:i:s',
            '/^\d{1,2}-\d{1,2}-\d{4}$/' => 'd-m-Y',
        ];

        foreach ($patterns as $pattern => $format) {
            if (preg_match($pattern, $dateString)) {
                return $format;
            }
        }

        return null;
    }

    /**
     * Validate that the parsed date makes sense
     *
     * @param Carbon $date
     * @param string $originalString
     * @param string $format
     * @return bool
     */
    private function validateParsedDate(Carbon $date, string $originalString, string $format): bool
    {
        try {
            // Check if formatting the date back gives us the original string
            $reformatted = $date->format($format);

            // For some formats, we need to be more flexible
            if ($reformatted === $originalString) {
                return true;
            }

            // Handle cases where single digits might have leading zeros
            if ($this->isFlexibleMatch($reformatted, $originalString)) {
                return true;
            }

            // Check if the date is reasonable (not too far in past/future)
            $now = Carbon::now();
            $yearsDiff = abs($now->year - $date->year);

            // Allow dates within 50 years of current date
            return $yearsDiff <= 50;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if two date strings are flexibly equivalent (accounting for leading zeros)
     *
     * @param string $formatted
     * @param string $original
     * @return bool
     */
    private function isFlexibleMatch(string $formatted, string $original): bool
    {
        // Remove leading zeros from both strings for comparison
        $normalizedFormatted = preg_replace('/\b0+(\d)/', '$1', $formatted);
        $normalizedOriginal = preg_replace('/\b0+(\d)/', '$1', $original);

        return $normalizedFormatted === $normalizedOriginal;
    }

    /**
     * Match Decta transactions with payment gateway data
     *
     * @param int $fileId Optional file ID to limit matching
     * @return array Matching results
     */
    public function matchTransactions(?int $fileId = null): array
    {
        $results = [
            'processed' => 0,
            'matched' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        try {
            // Get unmatched transactions
            $query = DectaTransaction::unmatched();

            if ($fileId) {
                $query->where('decta_file_id', $fileId);
            }

            $transactions = $query->get();
            $results['processed'] = $transactions->count();

            Log::info('Starting transaction matching', [
                'file_id' => $fileId,
                'transaction_count' => $results['processed'],
            ]);

            foreach ($transactions as $transaction) {
                try {
                    $transaction->markAsProcessing();

                    $gatewayData = $this->findMatchingGatewayTransaction($transaction);

                    if ($gatewayData) {
                        $transaction->markAsMatched($gatewayData);
                        $results['matched']++;

                        Log::debug('Transaction matched', [
                            'decta_payment_id' => $transaction->payment_id,
                            'gateway_data' => $gatewayData,
                        ]);
                    } else {
                        $transaction->markAsFailed('No matching gateway transaction found', [
                            'search_criteria' => $this->getSearchCriteria($transaction),
                        ]);
                        $results['failed']++;
                    }

                } catch (Exception $e) {
                    $transaction->markAsFailed($e->getMessage());
                    $results['failed']++;
                    $results['errors'][] = "Transaction {$transaction->payment_id}: {$e->getMessage()}";

                    Log::error('Error matching transaction', [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $transaction->payment_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Completed transaction matching', [
                'file_id' => $fileId,
                'results' => $results,
            ]);

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('Failed to match transactions', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $results;
    }

    /**
     * Find matching transaction in payment gateway database
     *
     * @param DectaTransaction $dectaTransaction
     * @return array|null
     */
    private function findMatchingGatewayTransaction(DectaTransaction $dectaTransaction): ?array
    {
        try {
            // Build search criteria based on available data
            $searchCriteria = $this->getSearchCriteria($dectaTransaction);

            // Search in payment_gateway database with more complete data selection
            $query = DB::connection('payment_gateway_mysql')
                ->table('transactions')
                ->rightJoin('bank_response', 'transactions.trx_id', '=', 'bank_response.tid')
                ->select([
                    'transactions.tid as transaction_id',
                    'transactions.account_id',
                    'transactions.shop_id',
                    'transactions.trx_id as trx_id',
                    'transactions.bank_amount',
                    'transactions.bank_currency',
                    'transactions.transaction_status',
                    'transactions.added as transaction_date',
                    'bank_response.added as bank_response_date',
                    'bank_response.bank_trx_id as bank_response_bank_trx_id',
                    'bank_response.bank_message as bank_response_bank_message',
                ]);

            // Apply search criteria with fallback strategies
            $matches = $this->applySearchCriteria($query, $searchCriteria);

            if ($matches->count() === 1) {
                // Perfect match found
                $match = $matches->first();
                return $this->formatGatewayData($match);
            } elseif ($matches->count() > 1) {
                // Multiple matches - try to narrow down
                $bestMatch = $this->selectBestMatch($matches, $dectaTransaction);
                return $bestMatch ? $this->formatGatewayData((object)$bestMatch) : null;
            }

            // Try alternative search strategies
            return $this->tryAlternativeSearch($dectaTransaction);

        } catch (Exception $e) {
            Log::error('Error searching for matching gateway transaction', [
                'decta_payment_id' => $dectaTransaction->payment_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    /**
     * Format gateway data for storage
     *
     * @param object $gatewayTransaction
     * @return array
     */
    private function formatGatewayData(object $gatewayTransaction): array
    {
        return [
            'transaction_id' => $gatewayTransaction->transaction_id,
            'account_id' => $gatewayTransaction->account_id,
            'shop_id' => $gatewayTransaction->shop_id,
            'trx_id' => $gatewayTransaction->trx_id,
            'transaction_date' => $gatewayTransaction->transaction_date ?? null,
            'bank_response_date' => $gatewayTransaction->bank_response_date ?? null,
            'bank_amount' => $gatewayTransaction->bank_amount ?? null,
            'bank_currency' => $gatewayTransaction->bank_currency ?? null,
            'transaction_status' => $gatewayTransaction->transaction_status ?? null,
        ];
    }
    /**
     * Get search criteria from Decta transaction
     *
     * @param DectaTransaction $transaction
     * @return array
     */
    private function getSearchCriteria(DectaTransaction $transaction): array
    {
        return [
            'payment_id' => $transaction->payment_id,
            'amount' => $transaction->tr_amount, // Already in cents
            'currency' => $transaction->tr_ccy,
        ];
    }

    /**
     * Apply search criteria to query with multiple strategies
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $criteria
     * @return \Illuminate\Support\Collection
     */
    private function applySearchCriteria($query, array $criteria)
    {
        $originalQuery = clone $query;

        // Strategy 3: Amount + Currency + Date range
        if (!empty($criteria['payment_id'])) {
            $amountQuery = clone $originalQuery;
            $amountQuery->where('bank_response.bank_trx_id', $criteria['payment_id']);

            // Add currency if available
            if (!empty($criteria['currency'])) {
                $amountQuery->where('transactions.bank_currency', $criteria['currency']);
            }

            // Add date range search (within same day)
            if ($criteria['amount']) {
                $amountQuery->where('amount', $criteria['amount']);
            }

            $results = $amountQuery->get();

            if ($results->count() > 0) {
                Log::info('Found match using amount, currency, and date', [
                    'amount' => $criteria['amount'],
                    'currency' => $criteria['currency'],
                    'matches' => $results->count()
                ]);
                return $results;
            }
        }

        return collect();
    }

    /**
     * Select best match from multiple candidates
     *
     * @param \Illuminate\Support\Collection $matches
     * @param DectaTransaction $dectaTransaction
     * @return array|null
     */
    private function selectBestMatch($matches, DectaTransaction $dectaTransaction): ?array
    {
        // Score matches based on how many criteria match
        $scoredMatches = $matches->map(function ($match) use ($dectaTransaction) {
            $score = 0;

            // Exact amount match
            if ($match->bank_amount == $dectaTransaction->tr_amount) {
                $score += 10;
            }

            // Currency match
            if ($match->bank_currency === $dectaTransaction->tr_ccy) {
                $score += 5;
            }

            // Time proximity (closer is better)
            if ($dectaTransaction->tr_date_time && $match->transaction_date) {
                $timeDiff = abs(strtotime($match->transaction_date) - strtotime($dectaTransaction->tr_date_time));
                if ($timeDiff < 3600) { // Within 1 hour
                    $score += 3;
                } elseif ($timeDiff < 86400) { // Within 1 day
                    $score += 1;
                }
            }

            $match->match_score = $score;
            return $match;
        });

        // Return the highest scored match
        $bestMatch = $scoredMatches->sortByDesc('match_score')->first();

        return $bestMatch && $bestMatch->match_score > 0 ? (array)$bestMatch : null;
    }


    /**
     * Try alternative search strategies for difficult matches
     *
     * @param DectaTransaction $dectaTransaction
     * @return array|null
     */
    private function tryAlternativeSearch(DectaTransaction $dectaTransaction): ?array
    {
        // Strategy 4: Fuzzy amount matching (within 1% tolerance)
        if ($dectaTransaction->tr_amount && $dectaTransaction->tr_date_time) {
            $amount = $dectaTransaction->tr_amount;
            $tolerance = max(1, round($amount * 0.01)); // 1% tolerance, minimum 1 cent
            $date = Carbon::parse($dectaTransaction->tr_date_time);

            $result = DB::connection('payment_gateway_mysql')
                ->table('transactions')
                ->leftJoin('bank_response', 'transactions.trx_id', '=', 'bank_response.tid')
                ->whereBetween('transactions.bank_amount', [$amount - $tolerance, $amount + $tolerance])
                ->whereDate('transactions.added', $date->toDateString())
                ->select([
                    'transactions.id as transaction_id',
                    'transactions.account_id',
                    'transactions.shop_id',
                    'transactions.trx_id as trx_id',
                    'transactions.added as transaction_date',
                    'bank_response.created as bank_response_date',
                ])
                ->first();

            if ($result) {
                Log::info('Found match using fuzzy amount matching', [
                    'decta_amount' => $amount,
                    'tolerance' => $tolerance,
                    'gateway_transaction_id' => $result->transaction_id
                ]);
                return $this->formatGatewayData($result);
            }
        }

        return null;
    }

    /**
     * Get processing statistics for a file
     *
     * @param int $fileId
     * @return array
     */
    public function getProcessingStats(int $fileId): array
    {
        return DectaTransaction::getMatchingStats($fileId);
    }
    /**
     * Get existing payment IDs for a file to avoid duplicates
     *
     * @param DectaFile $file
     * @return array
     */
    private function getExistingPaymentIds(DectaFile $file): array
    {
        return DectaTransaction::where('decta_file_id', $file->id)
            ->pluck('payment_id')
            ->toArray();
    }
}
