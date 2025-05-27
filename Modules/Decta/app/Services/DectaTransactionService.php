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
        $query = DB::connection('payment_gateway_mysql')
            ->table('transactions')
            ->leftJoin('bank_response', 'transactions.trx_id', '=', 'bank_response.tid')
            ->select([
                'transactions.tid as transaction_id',
                'transactions.account_id',
                'transactions.shop_id',
                'transactions.trx_id as trx_id',
                'transactions.added as transaction_date',
                'bank_response.added as transaction_date',
            ])->where('bank_response.bank_trx_id', $rowData['PAYMENT_ID'])
            ->where('bank_response.added', $rowData['TR_DATE_TIME'])
            ->where('transactions.amount', (((int)$rowData['TR_AMOUNT']) * 100))
            ->first();
        $transactionData = [
            'decta_file_id' => $file->id,
            'gateway_account_id' => $query->account_id,
            'gateway_shop_id' => $query->shop_id,
            'gateway_trx_id' => $query->trx_id,
            'gateway_transaction_id' => $query->transaction_id,
            'gateway_transaction_date' => $query->transaction_date,
            'gateway_bank_response_date' => $query->transaction_date,
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
     * Enhanced CSV processing with resume capability
     *
     * @param DectaFile $file
     * @param string $content
     * @param int $skipRows Number of rows to skip (for resume)
     * @return array Processing results
     */
    public function processCsvFileWithResume(DectaFile $file, string $content, int $skipRows = 0): array
    {
        $results = [
            'total_rows' => 0,
            'processed' => 0,
            'failed' => 0,
            'skipped' => $skipRows,
            'errors' => [],
        ];

        try {
            Log::info('Starting CSV processing with resume capability', [
                'file_id' => $file->id,
                'filename' => $file->filename,
                'content_length' => strlen($content),
                'skip_rows' => $skipRows,
            ]);

            // Parse CSV content
            $lines = $this->parseContentToLines($content);

            if (empty($lines)) {
                throw new Exception('CSV file appears to be empty or unreadable');
            }

            // Get headers from first line
            $headerLine = array_shift($lines);
            $headers = $this->parseCsvLine($headerLine);
            $headers = $this->normalizeHeaders($headers);

            if (empty($headers)) {
                throw new Exception('No headers found in CSV file');
            }

            $results['total_rows'] = count($lines);

            // Skip already processed rows if resuming
            if ($skipRows > 0) {
                $lines = array_slice($lines, $skipRows);
                Log::info("Skipping {$skipRows} already processed rows");
            }

            if (empty($lines)) {
                Log::info('No remaining rows to process');
                return $results;
            }

            Log::info('Processing remaining rows', [
                'file_id' => $file->id,
                'remaining_rows' => count($lines),
                'headers' => $headers,
            ]);

            // Process each remaining row
            foreach ($lines as $index => $line) {
                if (empty(trim($line))) {
                    continue;
                }

                try {
                    $data = $this->parseCsvLine($line);

                    // Handle column count mismatch
                    if (count($data) !== count($headers)) {
                        if (count($data) < count($headers)) {
                            $data = array_pad($data, count($headers), '');
                        } else {
                            $data = array_slice($data, 0, count($headers));
                        }
                    }

                    $rowData = array_combine($headers, $data);
                    if ($rowData === false) {
                        throw new Exception("Failed to combine headers with data");
                    }

                    // Check for duplicates based on payment_id
                    if (isset($rowData['PAYMENT_ID']) && !empty($rowData['PAYMENT_ID'])) {
                        $exists = DectaTransaction::where('decta_file_id', $file->id)
                            ->where('payment_id', $rowData['PAYMENT_ID'])
                            ->exists();

                        if ($exists) {
                            Log::debug('Skipping duplicate transaction', [
                                'payment_id' => $rowData['PAYMENT_ID'],
                                'file_id' => $file->id,
                            ]);
                            continue;
                        }
                    }

                    // Store the transaction
                    $transaction = $this->storeTransaction($file, $rowData);
                    $results['processed']++;

                    Log::debug('Transaction stored', [
                        'file_id' => $file->id,
                        'row' => $skipRows + $index + 2, // +2 for header and 0-based index
                        'transaction_id' => $transaction->id,
                        'payment_id' => $transaction->payment_id,
                    ]);

                } catch (Exception $e) {
                    $results['failed']++;
                    $error = "Row " . ($skipRows + $index + 2) . ": " . $e->getMessage();
                    $results['errors'][] = $error;

                    Log::warning('Failed to process CSV row', [
                        'file_id' => $file->id,
                        'row' => $skipRows + $index + 2,
                        'error' => $e->getMessage(),
                        'raw_line' => $line ?? 'empty',
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
     * Count total rows in CSV content
     *
     * @param string $content
     * @return int
     */
    public function countCsvRows(string $content): int
    {
        $lines = $this->parseContentToLines($content);
        return max(0, count($lines) - 1); // Subtract header row
    }

    /**
     * Get file processing progress
     *
     * @param DectaFile $file
     * @return array
     */
    public function getFileProgress(DectaFile $file): array
    {
        $content = Storage::disk('decta')->get($file->local_path);
        if (!$content) {
            return [
                'total_rows' => 0,
                'processed_rows' => 0,
                'completion_percentage' => 0,
                'can_resume' => false,
            ];
        }

        $totalRows = $this->countCsvRows($content);
        $processedRows = $file->dectaTransactions()->count();
        $completionPercentage = $totalRows > 0 ? ($processedRows / $totalRows) * 100 : 0;

        return [
            'total_rows' => $totalRows,
            'processed_rows' => $processedRows,
            'remaining_rows' => max(0, $totalRows - $processedRows),
            'completion_percentage' => round($completionPercentage, 2),
            'can_resume' => $processedRows < $totalRows && $processedRows > 0,
        ];
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
    private function parseDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Try common date formats
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
                'Y/m/d H:i:s',
                'Y/m/d',
            ];

            foreach ($formats as $format) {
                $date = Carbon::createFromFormat($format, $dateString);
                if ($date !== false) {
                    return $date;
                }
            }

            // Fallback to Carbon's parsing
            return Carbon::parse($dateString);

        } catch (Exception $e) {
            Log::warning('Failed to parse date', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
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

            // Search in payment_gateway database
            $query = DB::connection('payment_gateway_mysql')
                ->table('transactions')
                ->leftJoin('bank_response', 'transactions.trx_id', '=', 'bank_response.tid')
                ->select([
                    'transactions.id as transaction_id',
                    'transactions.account_id',
                    'transactions.shop_id',
                    'transactions.tid as trx_id',
                    'transactions.bank_amount',
                    'transactions.bank_currency',
                    'transactions.added as transaction_date',
                ]);

            // Apply search criteria with fallback strategies
            $matches = $this->applySearchCriteria($query, $searchCriteria);

            if ($matches->count() === 1) {
                // Perfect match found
                return $matches->first();
            } elseif ($matches->count() > 1) {
                // Multiple matches - try to narrow down
                return $this->selectBestMatch($matches, $dectaTransaction);
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
     * Get search criteria from Decta transaction
     *
     * @param DectaTransaction $transaction
     * @return array
     */
    private function getSearchCriteria(DectaTransaction $transaction): array
    {
        return [
            'approval_id' => $transaction->tr_approval_id,
            'ret_ref_nr' => $transaction->tr_ret_ref_nr,
            'amount' => $transaction->tr_amount, // Already in cents
            'currency' => $transaction->tr_ccy,
            'transaction_date' => $transaction->tr_date_time,
            'merchant_id' => $transaction->merchant_id,
            'terminal_id' => $transaction->terminal_id,
        ];
    }

    /**
     * Apply search criteria to query
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $criteria
     * @return \Illuminate\Support\Collection
     */
    private function applySearchCriteria($query, array $criteria)
    {
        // Primary search: approval_id and amount
        if (!empty($criteria['amount'])) {
            $query->where('transactions.bank_amount', $criteria['amount']);
        }

        // Add currency if available
        if (!empty($criteria['currency'])) {
            $query->where('transactions.bank_currency', $criteria['currency']);
        }

        // Add date range search (within same day)
        if ($criteria['transaction_date']) {
            $date = Carbon::parse($criteria['transaction_date']);
            $query->whereDate('transactions.added', $date->toDateString());
        }

        return $query->get();
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
     * Try alternative search strategies
     *
     * @param DectaTransaction $dectaTransaction
     * @return array|null
     */
    private function tryAlternativeSearch(DectaTransaction $dectaTransaction): ?array
    {

        if ($dectaTransaction->tr_amount && $dectaTransaction->tr_date_time) {
            $date = Carbon::parse($dectaTransaction->tr_date_time);

            $result = DB::connection('payment_gateway_mysql')
                ->table('transactions')
                ->leftJoin('bank_response', 'transactions.trx_id', '=', 'bank_response.tid')
                ->where('transactions.bank_amount', $dectaTransaction->tr_amount)
                ->whereDate('transactions.added', $date->toDateString())
                ->select([
                    'transactions.tid as transaction_id',
                    'transactions.account_id',
                    'transactions.shop_id',
                    'transactions.trx_id as trx_id',
                ])
                ->first();

            if ($result) {
                return (array)$result;
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

}
