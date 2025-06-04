<?php

namespace Modules\Decta\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Models\DectaFile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class DectaSftpViewController extends Controller
{
    protected $sftpService;
    protected $fileRepository;

    public function __construct(
        DectaSftpService $sftpService,
        DectaFileRepository $fileRepository
    ) {
        $this->sftpService = $sftpService;
        $this->fileRepository = $fileRepository;
    }

    /**
     * Display the SFTP management page
     */
    public function index()
    {
        // Get recent files for display
        $recentFiles = DectaFile::with(['dectaTransactions' => function($query) {
            $query->selectRaw('decta_file_id, COUNT(*) as count,
                                   COUNT(*) FILTER (WHERE is_matched = true) as matched_count')
                ->groupBy('decta_file_id');
        }])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($file) {
                $transactionStats = $file->getTransactionStats();
                return [
                    'id' => $file->id,
                    'filename' => $file->filename,
                    'status' => $file->status,
                    'created_at' => $file->created_at,
                    'processed_at' => $file->processed_at,
                    'file_size' => $file->human_file_size,
                    'transaction_count' => $transactionStats['total'],
                    'matched_count' => $transactionStats['matched'],
                    'match_rate' => $transactionStats['match_rate']
                ];
            });

        // Get status counts
        $statusCounts = [
            'pending' => DectaFile::pending()->count(),
            'processing' => DectaFile::processing()->count(),
            'processed' => DectaFile::processed()->count(),
            'failed' => DectaFile::failed()->count()
        ];

        return view('decta::sftp.index', compact('recentFiles', 'statusCounts'));
    }

    /**
     * Test SFTP connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $connectionResult = $this->sftpService->testConnectionDetailed();

            return response()->json([
                'success' => $connectionResult['success'],
                'message' => $connectionResult['message'],
                'details' => $connectionResult['details'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('SFTP connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List files on the SFTP server
     */
    public function listFiles(Request $request): JsonResponse
    {
        try {
            $path = $request->get('path', '');
            $showAll = $request->boolean('show_all', false);

            $files = $this->sftpService->listRemoteFiles($path, $showAll);

            // Enhance file data with local status
            $enhancedFiles = array_map(function ($file) {
                $localFile = $this->fileRepository->findByFilename($file['name']);

                return array_merge($file, [
                    'is_downloaded' => $localFile !== null,
                    'local_status' => $localFile?->status,
                    'local_id' => $localFile?->id,
                    'processed_at' => $localFile?->processed_at?->toISOString(),
                    'transaction_count' => $localFile ? $localFile->getTransactionStats()['total'] : 0
                ]);
            }, $files);

            return response()->json([
                'success' => true,
                'files' => $enhancedFiles,
                'path' => $path,
                'file_count' => count($enhancedFiles)
            ]);

        } catch (\Exception $e) {
            Log::error('SFTP file listing failed', [
                'error' => $e->getMessage(),
                'path' => $request->get('path', ''),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download files from SFTP
     */
    public function download(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|string',
            'force' => 'boolean'
        ]);

        try {
            $files = $request->input('files');
            $force = $request->boolean('force', false);
            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($files as $filename) {
                try {
                    // Check if file already exists unless forcing
                    if (!$force && $this->fileRepository->existsByFilename($filename)) {
                        $results[] = [
                            'filename' => $filename,
                            'success' => false,
                            'message' => 'File already exists (use force to re-download)'
                        ];
                        $errorCount++;
                        continue;
                    }

                    $result = $this->sftpService->downloadFileDetailed($filename);

                    if ($result['success']) {
                        // Create or update file record
                        $fileData = [
                            'filename' => $filename,
                            'original_path' => $result['remote_path'],
                            'local_path' => $result['local_path'],
                            'file_size' => $result['file_size'],
                            'file_type' => pathinfo($filename, PATHINFO_EXTENSION),
                            'status' => DectaFile::STATUS_PENDING,
                            'metadata' => [
                                'download_date' => Carbon::now()->toISOString(),
                                'target_date' => $this->extractTargetDateFromFilename($filename),
                                'sftp_downloaded' => true
                            ]
                        ];

                        if ($force && $this->fileRepository->existsByFilename($filename)) {
                            // Update existing record
                            $existingFile = $this->fileRepository->findByFilename($filename);
                            $existingFile->update($fileData);
                        } else {
                            // Create new record
                            $this->fileRepository->create($fileData);
                        }

                        $successCount++;
                    } else {
                        $errorCount++;
                    }

                    $results[] = $result;

                } catch (\Exception $e) {
                    $results[] = [
                        'filename' => $filename,
                        'success' => false,
                        'message' => 'Download failed: ' . $e->getMessage()
                    ];
                    $errorCount++;

                    Log::error('Individual file download failed', [
                        'filename' => $filename,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "Download completed: {$successCount} successful, {$errorCount} failed";

            return response()->json([
                'success' => $errorCount === 0,
                'message' => $message,
                'results' => $results,
                'summary' => [
                    'total' => count($files),
                    'successful' => $successCount,
                    'failed' => $errorCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk download failed', [
                'error' => $e->getMessage(),
                'files' => $request->input('files', []),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk download failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process downloaded files
     */
    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'file_ids' => 'required|array|min:1',
            'file_ids.*' => 'required|integer|exists:decta_files,id',
            'skip_matching' => 'boolean'
        ]);

        try {
            $fileIds = $request->input('file_ids');
            $skipMatching = $request->boolean('skip_matching', false);

            // Run the processing command in background for these specific files
            $command = 'decta:process-files';
            $params = [];

            // Add file IDs
            foreach ($fileIds as $fileId) {
                $params[] = "--file-id={$fileId}";
            }

            if ($skipMatching) {
                $params[] = '--skip-matching';
            }

            // Execute the command
            $exitCode = Artisan::call($command, array_merge(['--no-interaction' => true], $params));

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Files queued for processing successfully',
                    'processed_files' => count($fileIds)
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Processing command returned error code: ' . $exitCode
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('File processing failed', [
                'error' => $e->getMessage(),
                'file_ids' => $request->input('file_ids', []),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a processed file for viewing
     */
    public function downloadFile(Request $request)
    {
        $request->validate([
            'file_id' => 'required|integer|exists:decta_files,id'
        ]);

        try {
            $file = $this->fileRepository->findById($request->input('file_id'));

            if (!$file) {
                abort(404, 'File not found');
            }

            $content = $this->fileRepository->getFileContent($file);

            if ($content === null) {
                abort(404, 'File content not accessible');
            }

            $headers = [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="' . $file->filename . '"',
            ];

            return response($content, 200, $headers);

        } catch (\Exception $e) {
            Log::error('File download failed', [
                'file_id' => $request->input('file_id'),
                'error' => $e->getMessage()
            ]);

            abort(500, 'File download failed: ' . $e->getMessage());
        }
    }

    /**
     * Get real-time status updates
     */
    public function getStatus(): JsonResponse
    {
        try {
            $statusCounts = [
                'pending' => DectaFile::pending()->count(),
                'processing' => DectaFile::processing()->count(),
                'processed' => DectaFile::processed()->count(),
                'failed' => DectaFile::failed()->count()
            ];

            $recentActivity = DectaFile::orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(['id', 'filename', 'status', 'updated_at'])
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'filename' => $file->filename,
                        'status' => $file->status,
                        'updated_at' => $file->updated_at->diffForHumans()
                    ];
                });

            return response()->json([
                'success' => true,
                'status_counts' => $statusCounts,
                'recent_activity' => $recentActivity,
                'timestamp' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract target date from filename (assuming date is in filename)
     */
    private function extractTargetDateFromFilename(string $filename): ?string
    {
        // Common patterns: YYYYMMDD, YYYY-MM-DD, YYYY_MM_DD, etc.
        $patterns = [
            '/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/',
            '/(\d{2})[-_]?(\d{2})[-_]?(\d{4})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filename, $matches)) {
                try {
                    if (strlen($matches[1]) === 4) {
                        // YYYY-MM-DD format
                        return Carbon::createFromFormat('Y-m-d', "{$matches[1]}-{$matches[2]}-{$matches[3]}")->toDateString();
                    } else {
                        // DD-MM-YYYY format
                        return Carbon::createFromFormat('d-m-Y', "{$matches[1]}-{$matches[2]}-{$matches[3]}")->toDateString();
                    }
                } catch (\Exception $e) {
                    // Invalid date, continue to next pattern
                    continue;
                }
            }
        }

        return null;
    }
}
