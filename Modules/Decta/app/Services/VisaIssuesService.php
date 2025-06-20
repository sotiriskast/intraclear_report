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

class VisaIssuesService
{
    /**
     * Visa Issues specific statuses to avoid conflicts with other processing
     */
    const STATUS_ISSUES_PENDING = 'issues_pending';
    const STATUS_ISSUES_PROCESSING = 'issues_processing';
    const STATUS_ISSUES_PROCESSED = 'issues_processed';
    const STATUS_ISSUES_FAILED = 'issues_failed';

    /**
     * Services and Repositories
     */
    protected DectaSftpService $sftpService;
    protected DectaTransactionRepository $transactionRepository;
    protected DectaFileRepository $fileRepository;

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
        $this->config = config('decta.visa_issues', []);
    }

    /**
     * List available files on SFTP server
     */
    public function listAvailableFiles(): array
    {
        try {
            $remotePath = $this->getRemotePath();
            $files = $this->sftpService->listFiles($remotePath);

            $availableFiles = [];
            $pattern = $this->getFilePattern();

            foreach ($files as $file) {
                $filename = basename($file['path']);

                // Check if filename matches our pattern
                if ($this->matchesFilePattern($filename)) {
                    $existingFile = $this->fileRepository->findByFilename($filename);

                    $availableFiles[] = [
                        'filename' => $filename,
                        'remote_path' => $file['path'],
                        'size' => $file['fileSize'] ?? 0,
                        'size_human' => $this->formatBytes($file['fileSize'] ?? 0),
                        'modified' => $file['lastModified'] ?? time(),
                        'modified_human' => Carbon::createFromTimestamp($file['lastModified'] ?? time())->format('M j, Y g:i A'),
                        'is_downloaded' => $existingFile !== null,
                        'local_status' => $existingFile?->status,
                        'local_file_id' => $existingFile?->id,
                        'date_range' => $this->extractDateRange($filename)
                    ];
                }
            }

            // Sort by modified date (newest first)
            usort($availableFiles, function ($a, $b) {
                return $b['modified'] <=> $a['modified'];
            });

            return $availableFiles;

        } catch (Exception $e) {
            Log::error('Failed to list Visa Issues files', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Download specific file by filename
     */
    public function downloadFile(string $filename, array $options = []): array
    {
        $force = $options['force'] ?? false;
        $isDryRun = $options['dry_run'] ?? false;

        $result = [
            'filename' => $filename,
            'success' => false,
            'downloaded' => false,
            'skipped' => false,
            'message' => ''
        ];

        try {
            // Validate filename pattern
            if (!$this->matchesFilePattern($filename)) {
                $result['message'] = 'Filename does not match expected pattern';
                return $result;
            }

            $remotePath = $this->getRemotePath() . '/' . $filename;

            // Check if file exists on SFTP
            if (!$this->checkFileExists($remotePath)) {
                $result['message'] = 'File not found on SFTP server';
                return $result;
            }

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
            $downloadResult = $this->sftpService->downloadFileDetailed($filename);

            if (!$downloadResult['success']) {
                $result['message'] = $downloadResult['message'] ?? 'Unknown download error';
                return $result;
            }

            // Create or update file record in database
            $fileRecord = $this->createOrUpdateFileRecord(
                $filename,
                $downloadResult,
                $force && $existingFile
            );

            $result['success'] = true;
            $result['downloaded'] = true;
            $result['message'] = 'Downloaded successfully';
            $result['file_record'] = $fileRecord;
            $result['local_path'] = $downloadResult['local_path'];

            Log::info('Visa Issues file downloaded', [
                'filename' => $filename,
                'file_id' => $fileRecord->id,
                'size' => $downloadResult['file_size'] ?? 0
            ]);

            return $result;

        } catch (Exception $e) {
            $result['message'] = 'Download failed: ' . $e->getMessage();
            Log::error('Visa Issues download failed', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $result;
        }
    }

    /**
     * Process Visa Issues files
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
     * Process a single file
     */
    public function processFile(DectaFile $file): array
    {
        try {
            // Mark as processing
            $file->update(['status' => self::STATUS_ISSUES_PROCESSING]);

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
                'status' => self::STATUS_ISSUES_PROCESSED,
                'processed_at' => Carbon::now(),
                'metadata' => array_merge($file->metadata ?? [], [
                    'visa_issues_processing' => [
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

            Log::info('Visa Issues file processed successfully', [
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
                'status' => self::STATUS_ISSUES_FAILED,
                'metadata' => array_merge($file->metadata ?? [], [
                    'visa_issues_processing' => [
                        'failed_at' => Carbon::now()->toISOString(),
                        'error_message' => $e->getMessage()
                    ]
                ])
            ]);

            Log::error('Visa Issues file processing failed', [
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

        // Filter by file type (Visa Issues files)
        $query->where(function ($q) {
            $q->where('file_type', 'visa_issues_csv')
                ->orWhere('filename', 'LIKE', 'INTCL_visa_sms_tr_det_%-%');
        });

        // Filter by status unless reprocessing
        if (!($options['reprocess'] ?? false)) {
            $statusMap = [
                'pending' => self::STATUS_ISSUES_PENDING,
                'failed' => self::STATUS_ISSUES_FAILED,
                'processed' => self::STATUS_ISSUES_PROCESSED,
                'processing' => self::STATUS_ISSUES_PROCESSING,
            ];

            $requestedStatuses = explode(',', $options['status'] ?? 'pending');
            $issuesStatuses = [];

            foreach ($requestedStatuses as $status) {
                $status = trim($status);
                if (isset($statusMap[$status])) {
                    $issuesStatuses[] = $statusMap[$status];
                }
            }

            if (!empty($issuesStatuses)) {
                $query->whereIn('status', $issuesStatuses);
            }
        }

        return $query->orderBy('created_at', 'desc')->get()->all();
    }

    /**
     * Get remote path for files
     */
    protected function getRemotePath(): string
    {
        return $this->config['sftp']['remote_path'] ?? '/in_file/Different issues';
    }

    /**
     * Get file pattern for matching
     */
    protected function getFilePattern(): string
    {
        return $this->config['sftp']['file_pattern'] ?? 'INTCL_visa_sms_tr_det_';
    }

    /**
     * Check if filename matches expected pattern
     */
    protected function matchesFilePattern(string $filename): bool
    {
        $pattern = $this->getFilePattern();

        // Check for date range pattern: INTCL_visa_sms_tr_det_YYYYMMDD-YYYYMMDD.csv
        return preg_match('/^' . preg_quote($pattern, '/') . '\d{8}-\d{8}\.csv$/', $filename);
    }

    /**
     * Extract date range from filename
     */
    protected function extractDateRange(string $filename): ?array
    {
        if (preg_match('/(\d{8})-(\d{8})/', $filename, $matches)) {
            try {
                $startDate = Carbon::createFromFormat('Ymd', $matches[1]);
                $endDate = Carbon::createFromFormat('Ymd', $matches[2]);

                return [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'start_formatted' => $startDate->format('M j, Y'),
                    'end_formatted' => $endDate->format('M j, Y'),
                    'period' => $startDate->format('M j') . ' - ' . $endDate->format('M j, Y')
                ];
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
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
    protected function createOrUpdateFileRecord(string $filename, array $downloadResult, bool $isUpdate = false): DectaFile
    {
        $dateRange = $this->extractDateRange($filename);

        $fileData = [
            'filename' => $filename,
            'original_path' => $downloadResult['remote_path'],
            'local_path' => $downloadResult['local_path'],
            'file_size' => $downloadResult['file_size'] ?? 0,
            'file_type' => 'visa_issues_csv',
            'status' => self::STATUS_ISSUES_PENDING,
            'metadata' => [
                'download_date' => Carbon::now()->toISOString(),
                'date_range' => $dateRange,
                'source_type' => 'visa_issues_interchange',
                'manual_download' => true
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

        Log::info('Updated transaction interchange from Issues file', [
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
        $query = DectaFile::where('file_type', 'visa_issues_csv')
            ->orWhere('filename', 'LIKE', 'INTCL_visa_sms_tr_det_%-%');

        if (isset($options['days'])) {
            $since = Carbon::now()->subDays($options['days']);
            $query->where('created_at', '>=', $since);
        }

        $files = $query->get();

        $stats = [
            'total_files' => $files->count(),
            'pending_files' => $files->where('status', self::STATUS_ISSUES_PENDING)->count(),
            'processed_files' => $files->where('status', self::STATUS_ISSUES_PROCESSED)->count(),
            'failed_files' => $files->where('status', self::STATUS_ISSUES_FAILED)->count(),
            'total_transactions_updated' => 0,
            'total_transactions_not_found' => 0,
            'total_errors' => 0
        ];

        foreach ($files as $file) {
            $metadata = $file->metadata['visa_issues_processing'] ?? [];
            $stats['total_transactions_updated'] += $metadata['transactions_updated'] ?? 0;
            $stats['total_transactions_not_found'] += $metadata['transactions_not_found'] ?? 0;
            $stats['total_errors'] += $metadata['errors'] ?? 0;
        }

        return $stats;
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }
}
