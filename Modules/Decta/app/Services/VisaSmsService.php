<?php

namespace Modules\Decta\Services;

use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Models\DectaTransaction;
use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class VisaSmsService
{
    /**
     * Services and Repositories
     */
    protected DectaSftpService $sftpService;
    protected DectaTransactionRepository $transactionRepository;
    protected DectaFileRepository $fileRepository;

    /**
     * Visa SMS specific statuses to avoid conflicts with regular Decta processing
     */
    const STATUS_VISA_PENDING = 'visa_pending';
    const STATUS_VISA_PROCESSING = 'visa_processing';
    const STATUS_VISA_PROCESSED = 'visa_processed';
    const STATUS_VISA_FAILED = 'visa_failed';

    /**
     * Configuration
     */
    protected array $config;

    /**
     * Constructor
     */
    public function __construct(
        DectaSftpService $sftpService,
        DectaTransactionRepository $transactionRepository,
        DectaFileRepository $fileRepository
    ) {
        $this->sftpService = $sftpService;
        $this->transactionRepository = $transactionRepository;
        $this->fileRepository = $fileRepository;
        $this->config = config('decta.visa_sms', []);
    }

    /**
     * Download Visa SMS files for specified dates
     */
    public function downloadFiles(array $options = []): array
    {
        $dates = $this->getDateRange($options);
        $isDryRun = $options['dry_run'] ?? false;
        $force = $options['force'] ?? false;

        $results = [
            'files_found' => 0,
            'files_downloaded' => 0,
            'files_skipped' => 0,
            'files_processed' => 0,
            'errors' => 0,
            'details' => []
        ];

        foreach ($dates as $date) {
            $filename = $this->generateFilename($date);
            $result = $this->downloadSingleFile($filename, $date, $isDryRun, $force);

            $results['details'][] = $result;

            if ($result['found']) {
                $results['files_found']++;
            }

            if ($result['downloaded']) {
                $results['files_downloaded']++;
            }

            if ($result['skipped']) {
                $results['files_skipped']++;
            }

            if ($result['processed']) {
                $results['files_processed']++;
            }

            if (isset($result['error'])) {
                $results['errors']++;
            }

            // Process immediately if requested and file was downloaded
            if (($options['process_immediately'] ?? false) && $result['downloaded'] && !$isDryRun) {
                $processResult = $this->processFile($result['file_record']);
                $result['process_result'] = $processResult;
                if ($processResult['success']) {
                    $results['files_processed']++;
                }
            }
        }

        return $results;
    }

    /**
     * Process Visa SMS files
     */
    public function processFiles(array $options = []): array
    {
        $files = $this->getFilesToProcess($options);
        $isDryRun = $options['dry_run'] ?? false;

        $results = [
            'files_processed' => 0,
            'files_failed' => 0,
            'total_transactions_updated' => 0,
            'details' => []
        ];

        foreach ($files as $file) {
            if ($isDryRun) {
                $result = $this->previewFileProcessing($file);
                $result['dry_run'] = true;
            } else {
                $result = $this->processFile($file);
                if ($result['success']) {
                    $results['files_processed']++;
                    $results['total_transactions_updated'] += $result['updated_count'] ?? 0;
                } else {
                    $results['files_failed']++;
                }
            }

            $results['details'][] = array_merge($result, [
                'file_id' => $file->id,
                'filename' => $file->filename
            ]);
        }

        return $results;
    }

    /**
     * Download a single file
     */
    protected function downloadSingleFile(string $filename, Carbon $date, bool $isDryRun = false, bool $force = false): array
    {
        $remotePath = $this->getRemotePath() . '/' . $filename;

        $result = [
            'filename' => $filename,
            'date' => $date->format('Y-m-d'),
            'found' => false,
            'downloaded' => false,
            'skipped' => false,
            'processed' => false
        ];

        try {
            // Check if file exists on SFTP
            if (!$this->checkFileExists($remotePath)) {
                $result['message'] = 'File not found on SFTP server';
                return $result;
            }

            $result['found'] = true;

            // Check if already downloaded (unless forcing)
            $existingFile = $this->fileRepository->findByFilename($filename);
            if ($existingFile && !$force) {
                $result['skipped'] = true;
                $result['message'] = "File already downloaded (ID: {$existingFile->id})";
                $result['file_record'] = $existingFile;
                return $result;
            }

            if ($isDryRun) {
                $result['message'] = '[DRY RUN] Would download file';
                return $result;
            }

            // Download the file
            $downloadResult = $this->sftpService->downloadFileDetailed($filename, $date->format('Y-m-d'));

            if (!$downloadResult['success']) {
                $result['error'] = $downloadResult['message'] ?? 'Unknown download error';
                return $result;
            }

            // Create or update file record in database
            $fileRecord = $this->createOrUpdateFileRecord(
                $filename,
                $downloadResult,
                $date,
                $force && $existingFile
            );

            $result['downloaded'] = true;
            $result['message'] = 'Downloaded successfully';
            $result['file_record'] = $fileRecord;
            $result['local_path'] = $downloadResult['local_path'];

            return $result;

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            Log::error('Visa SMS download failed', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $result;
        }
    }

    /**
     * Process a single file
     */
    public function processFile(DectaFile $file): array
    {
        try {
            $csvData = $this->readCsvFile($file);

            $result = [
                'success' => false,
                'total_rows' => count($csvData),
                'updated_count' => 0,
                'not_found_count' => 0,
                'error_count' => 0
            ];

            DB::beginTransaction();

            foreach ($csvData as $rowIndex => $row) {
                try {
                    $updateResult = $this->updateTransactionInterchange($row);

                    switch ($updateResult) {
                        case 'updated':
                            $result['updated_count']++;
                            break;
                        case 'not_found':
                            $result['not_found_count']++;
                            break;
                        default:
                            $result['error_count']++;
                    }

                } catch (Exception $e) {
                    $result['error_count']++;
                    Log::warning('Error updating transaction interchange', [
                        'file_id' => $file->id,
                        'row_index' => $rowIndex,
                        'payment_id' => $row['PAYMENT_ID'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Update file status
            $file->update([
                'status' => self::STATUS_VISA_PROCESSED,
                'processed_at' => Carbon::now(),
                'metadata' => array_merge($file->metadata ?? [], [
                    'visa_sms_processing' => [
                        'processed_at' => Carbon::now()->toISOString(),
                        'total_rows' => $result['total_rows'],
                        'transactions_updated' => $result['updated_count'],
                        'transactions_not_found' => $result['not_found_count'],
                        'errors' => $result['error_count']
                    ]
                ])
            ]);

            DB::commit();
            $result['success'] = true;

            Log::info('Visa SMS file processed successfully', [
                'file_id' => $file->id,
                'filename' => $file->filename,
                'updated_count' => $result['updated_count'],
                'not_found_count' => $result['not_found_count'],
                'error_count' => $result['error_count']
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollBack();

            $file->update([
                'status' => self::STATUS_VISA_FAILED,
                'metadata' => array_merge($file->metadata ?? [], [
                    'visa_sms_processing' => [
                        'failed_at' => Carbon::now()->toISOString(),
                        'error_message' => $e->getMessage()
                    ]
                ])
            ]);

            Log::error('Visa SMS file processing failed', [
                'file_id' => $file->id,
                'filename' => $file->filename,
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
     * Preview file processing without making changes
     */
    public function previewFileProcessing(DectaFile $file): array
    {
        try {
            $csvData = $this->readCsvFile($file);
            $updateCount = 0;

            foreach ($csvData as $row) {
                $paymentId = $row['PAYMENT_ID'] ?? null;
                $interchange = $row['INTERCHANGE'] ?? null;

                if (!empty($paymentId) && $interchange !== null) {
                    $transaction = $this->transactionRepository->findByPaymentId($paymentId);
                    if ($transaction) {
                        $updateCount++;
                    }
                }
            }

            return [
                'total_rows' => count($csvData),
                'update_count' => $updateCount,
                'success' => true
            ];

        } catch (Exception $e) {
            return [
                'total_rows' => 0,
                'update_count' => 0,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get date range based on options
     */
    protected function getDateRange(array $options): array
    {
        $dates = [];

        if (isset($options['date'])) {
            // Specific date provided
            try {
                $dates[] = Carbon::createFromFormat('Y-m-d', $options['date']);
            } catch (Exception $e) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD");
            }
        } else {
            // Default: check yesterday (reports are available 1 day back)
            // If today is 20/6, we check for files from 19/6
            $daysBack = $options['days_back'] ?? 7;

            // Start from yesterday
            for ($i = 1; $i <= $daysBack; $i++) {
                $dates[] = Carbon::now()->subDays($i);
            }
        }

        return $dates;
    }

    /**
     * Get files to process based on options
     */
    protected function getFilesToProcess(array $options): array
    {
        $query = DectaFile::query();

        // Filter by file ID if specified
        if (isset($options['file_id'])) {
            $file = $this->fileRepository->findById((int) $options['file_id']);
            return $file ? [$file] : [];
        }

        // Filter by filename if specified
        if (isset($options['filename'])) {
            $file = $this->fileRepository->findByFilename($options['filename']);
            return $file ? [$file] : [];
        }

        // Filter by file type (Visa SMS files)
        $query->where(function ($q) {
            $q->where('file_type', 'visa_sms_csv')
                ->orWhere('filename', 'LIKE', 'INTCL_visa_sms_tr_det_%');
        });

        // Filter by status unless reprocessing
        if (!($options['reprocess'] ?? false)) {
            $statusMap = [
                'pending' => self::STATUS_VISA_PENDING,
                'failed' => self::STATUS_VISA_FAILED,
                'processed' => self::STATUS_VISA_PROCESSED,
                'processing' => self::STATUS_VISA_PROCESSING,
            ];

            $requestedStatuses = explode(',', $options['status'] ?? 'pending');
            $visaStatuses = [];

            foreach ($requestedStatuses as $status) {
                $status = trim($status);
                if (isset($statusMap[$status])) {
                    $visaStatuses[] = $statusMap[$status];
                }
            }

            if (!empty($visaStatuses)) {
                $query->whereIn('status', $visaStatuses);
            }
        }

        return $query->orderBy('created_at', 'desc')->get()->all();
    }

    /**
     * Generate filename for a given date
     */
    protected function generateFilename(Carbon $date): string
    {
        $prefix = $this->config['sftp']['file_prefix'] ?? 'INTCL_visa_sms_tr_det_';
        $extension = $this->config['sftp']['file_extension'] ?? '.csv';

        return $prefix . $date->format('Ymd') . $extension;
    }

    /**
     * Get remote path for files
     */
    protected function getRemotePath(): string
    {
        return $this->config['sftp']['remote_path'] ?? '/in_file/reports';
    }

    /**
     * Check if file exists on SFTP server
     */
    protected function checkFileExists(string $remotePath): bool
    {
        try {
            $files = $this->sftpService->listFiles(dirname($remotePath));
            $filename = basename($remotePath);

            foreach ($files as $file) {
                if (basename($file['path']) === $filename) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            Log::warning('Error checking file existence', [
                'remote_path' => $remotePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create or update file record in database
     */
    protected function createOrUpdateFileRecord(string $filename, array $downloadResult, Carbon $date, bool $isUpdate = false): DectaFile
    {
        $fileData = [
            'filename' => $filename,
            'original_path' => $downloadResult['remote_path'],
            'local_path' => $downloadResult['local_path'],
            'file_size' => $downloadResult['file_size'] ?? 0,
            'file_type' => 'visa_sms_csv',
            'status' => self::STATUS_VISA_PENDING,
            'metadata' => [
                'download_date' => Carbon::now()->toISOString(),
                'target_date' => $date->format('Y-m-d'),
                'source_type' => 'visa_sms_interchange',
                'auto_downloaded' => true
            ]
        ];

        if ($isUpdate) {
            // Update existing record
            $existingFile = $this->fileRepository->findByFilename($filename);
            $existingFile->update($fileData);
            return $existingFile;
        } else {
            // Create new record
            return $this->fileRepository->create($fileData);
        }
    }

    /**
     * Read and parse CSV file
     */
    protected function readCsvFile(DectaFile $file): array
    {
        $localPath = $file->local_path;

        if (!Storage::disk('decta')->exists($localPath)) {
            throw new Exception("Local file not found: {$localPath}");
        }

        $fileContent = Storage::disk('decta')->get($localPath);
        return $this->parseCsvContent($fileContent);
    }

    /**
     * Parse CSV content into array
     */
    protected function parseCsvContent(string $content): array
    {
        $delimiter = $this->config['processing']['csv_delimiter'] ?? ';';

        $lines = explode("\n", $content);
        $header = str_getcsv(array_shift($lines), $delimiter);

        $data = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $row = str_getcsv($line, $delimiter);
            if (count($row) === count($header)) {
                $data[] = array_combine($header, $row);
            }
        }

        return $data;
    }

    /**
     * Update transaction interchange field
     */
    protected function updateTransactionInterchange(array $csvRow): string
    {
        // Get field mappings from config
        $fieldMappings = $this->config['processing']['field_mappings'] ?? [];
        $paymentIdField = $fieldMappings['payment_id'] ?? 'PAYMENT_ID';
        $interchangeField = $fieldMappings['interchange'] ?? 'INTERCHANGE';
        $targetField = $this->config['processing']['matching']['target_field'] ?? 'user_define_field2';

        // Extract data from CSV row
        $paymentId = $csvRow[$paymentIdField] ?? null;
        $interchange = $csvRow[$interchangeField] ?? null;

        if (empty($paymentId) || $interchange === null) {
            return 'invalid_data';
        }

        // Find existing transaction by payment_id
        $transaction = $this->transactionRepository->findByPaymentId($paymentId);

        if (!$transaction) {
            if ($this->config['processing']['matching']['log_not_found'] ?? false) {
                Log::debug('Transaction not found for payment ID', [
                    'payment_id' => $paymentId
                ]);
            }
            return 'not_found';
        }

        // Update the target field with interchange value
        $transaction->update([
            $targetField => $interchange,
        ]);

        Log::info('Updated transaction interchange', [
            'transaction_id' => $transaction->id,
            'payment_id' => $paymentId,
            'interchange' => $interchange,
            'target_field' => $targetField
        ]);

        return 'updated';
    }

    /**
     * Get configuration for a specific key
     */
    public function getConfig(string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key);
    }

    /**
     * Get processing statistics for files
     */
    public function getProcessingStats(array $options = []): array
    {
        $query = DectaFile::where('file_type', 'visa_sms_csv')
            ->orWhere('filename', 'LIKE', 'INTCL_visa_sms_tr_det_%');

        if (isset($options['days'])) {
            $since = Carbon::now()->subDays($options['days']);
            $query->where('created_at', '>=', $since);
        }

        $files = $query->get();

        $stats = [
            'total_files' => $files->count(),
            'pending_files' => $files->where('status', self::STATUS_VISA_PENDING)->count(),
            'processed_files' => $files->where('status', self::STATUS_VISA_PROCESSED)->count(),
            'failed_files' => $files->where('status', self::STATUS_VISA_FAILED)->count(),
            'total_transactions_updated' => 0,
            'total_transactions_not_found' => 0,
            'total_errors' => 0
        ];

        foreach ($files as $file) {
            $metadata = $file->metadata['visa_sms_processing'] ?? [];
            $stats['total_transactions_updated'] += $metadata['transactions_updated'] ?? 0;
            $stats['total_transactions_not_found'] += $metadata['transactions_not_found'] ?? 0;
            $stats['total_errors'] += $metadata['errors'] ?? 0;
        }

        return $stats;
    }
}
