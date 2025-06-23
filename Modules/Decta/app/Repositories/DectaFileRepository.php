<?php

namespace Modules\Decta\Repositories;

use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DectaFileRepository
{
    /**
     * The disk to use for file operations
     *
     * @var string
     */
    protected $diskName = 'decta';

    /**
     * Create a new file record
     *
     * @param array $data File data
     * @return DectaFile
     */
    public function create(array $data): DectaFile
    {
        return DectaFile::create($data);
    }

    /**
     * Find a file by filename
     *
     * @param string $filename
     * @return DectaFile|null
     */
    public function findByFilename(string $filename): ?DectaFile
    {
        return DectaFile::where('filename', $filename)->first();
    }

    /**
     * Find a file by ID
     *
     * @param int $id
     * @return DectaFile|null
     */
    public function findById(int $id): ?DectaFile
    {
        return DectaFile::find($id);
    }

    /**
     * Check if a file exists by filename
     *
     * @param string $filename
     * @return bool
     */
    public function existsByFilename(string $filename): bool
    {
        return DectaFile::where('filename', $filename)->exists();
    }

    /**
     * Get all pending files
     *
     * @return Collection
     */
    public function getPendingFiles(): Collection
    {
        return DectaFile::getPendingForTransaction()->get();
    }

    /**
     * Get all files with a specific status
     *
     * @param string $status
     * @return Collection
     */
    public function getFilesByStatus(string $status): Collection
    {
        return DectaFile::where('status', $status)->get();
    }

    /**
     * Update file status
     *
     * @param int $fileId
     * @param string $status
     * @param string|null $errorMessage
     * @return DectaFile
     */
    public function updateStatus(int $fileId, string $status, ?string $errorMessage = null): DectaFile
    {
        $file = DectaFile::findOrFail($fileId);

        $file->status = $status;

        if ($status === DectaFile::STATUS_PROCESSED) {
            $file->processed_at = now();
        }

        if ($errorMessage !== null) {
            $file->error_message = $errorMessage;
        }

        $file->save();

        return $file;
    }

    /**
     * Record file processing result
     *
     * @param int $fileId
     * @param bool $success
     * @param string|null $errorMessage
     * @return DectaFile
     */
    public function recordProcessingResult(int $fileId, bool $success, ?string $errorMessage = null): DectaFile
    {
        $file = DectaFile::findOrFail($fileId);

        if ($success) {
            $file->status = DectaFile::STATUS_PROCESSED;
            $file->processed_at = now();
        } else {
            $file->status = DectaFile::STATUS_FAILED;
            $file->error_message = $errorMessage;
        }

        $file->save();

        return $file;
    }

    /**
     * Get file content from storage with smart path resolution
     *
     * @param DectaFile $file
     * @return string|null
     */
    public function getFileContent(DectaFile $file): ?string
    {
        // Try the current path first
        if (Storage::disk($this->diskName)->exists($file->local_path)) {
            return Storage::disk($this->diskName)->get($file->local_path);
        }

        // If not found, try to locate the file in failed/processed directories
        $actualPath = $this->findActualFilePath($file);

        if ($actualPath) {
            Log::info('File found at different location, updating database', [
                'file_id' => $file->id,
                'old_path' => $file->local_path,
                'new_path' => $actualPath
            ]);

            // Update the database with the correct path
            $file->update(['local_path' => $actualPath]);

            return Storage::disk($this->diskName)->get($actualPath);
        }

        Log::warning('File not found in any expected location', [
            'file_id' => $file->id,
            'filename' => $file->filename,
            'expected_path' => $file->local_path,
            'searched_paths' => $this->getPossiblePaths($file->local_path)
        ]);

        return null;
    }

    /**
     * Find the actual file path by checking multiple possible locations
     *
     * @param DectaFile $file
     * @return string|null
     */
    public function findActualFilePath(DectaFile $file): ?string
    {
        $possiblePaths = $this->getPossiblePaths($file->local_path);

        foreach ($possiblePaths as $path) {
            if (Storage::disk($this->diskName)->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Get all possible paths where a file might be located
     *
     * @param string $originalPath
     * @return array
     */
    private function getPossiblePaths(string $originalPath): array
    {
        $filename = basename($originalPath);
        $directory = dirname($originalPath);
        $failedDir = config('decta.files.failed_dir', 'failed');
        $processedDir = config('decta.files.processed_dir', 'processed');

        return [
            $originalPath, // Current path
            $directory . '/' . $failedDir . '/' . $filename, // Failed directory
            $directory . '/' . $processedDir . '/' . $filename, // Processed directory
            // Also try without the year/month structure in case of path changes
            'files/' . $filename,
            'files/' . $failedDir . '/' . $filename,
            'files/' . $processedDir . '/' . $filename
        ];
    }

    /**
     * Delete file from storage using the decta disk
     *
     * @param DectaFile $file
     * @return bool
     */
    public function deleteFile(DectaFile $file): bool
    {
        // Find actual file path
        $actualPath = $this->findActualFilePath($file);

        if ($actualPath && Storage::disk($this->diskName)->exists($actualPath)) {
            Storage::disk($this->diskName)->delete($actualPath);
        }

        return $file->delete();
    }

    /**
     * Check if file exists in storage using the decta disk
     *
     * @param DectaFile $file
     * @return bool
     */
    public function fileExistsInStorage(DectaFile $file): bool
    {
        return $this->findActualFilePath($file) !== null;
    }

    /**
     * Get file size from storage using the decta disk
     *
     * @param DectaFile $file
     * @return int|false
     */
    public function getFileSize(DectaFile $file): int|false
    {
        $actualPath = $this->findActualFilePath($file);

        if ($actualPath && Storage::disk($this->diskName)->exists($actualPath)) {
            return Storage::disk($this->diskName)->size($actualPath);
        }

        return false;
    }

    /**
     * Get file's last modified time from storage using the decta disk
     *
     * @param DectaFile $file
     * @return int|false
     */
    public function getFileLastModified(DectaFile $file): int|false
    {
        $actualPath = $this->findActualFilePath($file);

        if ($actualPath && Storage::disk($this->diskName)->exists($actualPath)) {
            return Storage::disk($this->diskName)->lastModified($actualPath);
        }

        return false;
    }

    /**
     * Fix file paths for files that may have been moved but database not updated
     *
     * @param Collection|null $files
     * @return int Number of files fixed
     */
    public function fixFilePathsInBatch(?Collection $files = null): int
    {
        if ($files === null) {
            // Get all files that might need fixing
            $files = DectaFile::whereIn('status', [
                DectaFile::STATUS_FAILED,
                DectaFile::STATUS_PROCESSED,
                DectaFile::STATUS_PROCESSING
            ])->get();
        }

        $fixedCount = 0;

        foreach ($files as $file) {
            // Check if current path is wrong
            if (!Storage::disk($this->diskName)->exists($file->local_path)) {
                $actualPath = $this->findActualFilePath($file);

                if ($actualPath && $actualPath !== $file->local_path) {
                    $file->update(['local_path' => $actualPath]);
                    $fixedCount++;

                    Log::info('Fixed file path in batch operation', [
                        'file_id' => $file->id,
                        'filename' => $file->filename,
                        'old_path' => $file->local_path,
                        'new_path' => $actualPath
                    ]);
                }
            }
        }

        return $fixedCount;
    }

    /**
     * Get statistics on processed files
     *
     * @param int $days Number of days to look back
     * @return array
     */
    public function getStatistics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $totalFiles = DectaFile::where('created_at', '>=', $startDate)->count();
        $processedFiles = DectaFile::where('created_at', '>=', $startDate)
            ->where('status', DectaFile::STATUS_PROCESSED)
            ->count();
        $failedFiles = DectaFile::where('created_at', '>=', $startDate)
            ->where('status', DectaFile::STATUS_FAILED)
            ->count();
        $pendingFiles = DectaFile::where('created_at', '>=', $startDate)
            ->whereIn('status', [DectaFile::STATUS_PENDING, DectaFile::STATUS_PROCESSING])
            ->count();

        // Group by file type
        $byFileType = DectaFile::where('created_at', '>=', $startDate)
            ->selectRaw('file_type, COUNT(*) as count')
            ->groupBy('file_type')
            ->pluck('count', 'file_type')
            ->toArray();

        // Group by target date - Fixed for PostgreSQL
        $byDate = [];
        try {
            // Check if we're using PostgreSQL or MySQL
            $connection = DB::connection();
            $driverName = $connection->getDriverName();

            if ($driverName === 'pgsql') {
                // PostgreSQL syntax
                $byDate = DectaFile::where('created_at', '>=', $startDate)
                    ->whereNotNull('metadata')
                    ->whereRaw("metadata->>'target_date' IS NOT NULL")
                    ->selectRaw("metadata->>'target_date' as target_date, COUNT(*) as count")
                    ->groupBy(DB::raw("metadata->>'target_date'"))
                    ->pluck('count', 'target_date')
                    ->toArray();
            } else {
                // MySQL syntax
                $byDate = DectaFile::where('created_at', '>=', $startDate)
                    ->whereNotNull('metadata->target_date')
                    ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.target_date')) as target_date, COUNT(*) as count")
                    ->groupBy('target_date')
                    ->pluck('count', 'target_date')
                    ->toArray();
            }
        } catch (\Exception $e) {
            // Fallback: just return empty array for byDate if JSON parsing fails
            $byDate = [];
        }

        return [
            'total' => $totalFiles,
            'processed' => $processedFiles,
            'failed' => $failedFiles,
            'pending' => $pendingFiles,
            'success_rate' => $totalFiles > 0 ? ($processedFiles / $totalFiles) * 100 : 0,
            'by_file_type' => $byFileType,
            'by_date' => $byDate,
            'disk_used' => $this->diskName,
        ];
    }

    /**
     * Validate file integrity
     *
     * @param DectaFile $file
     * @return array
     */
    public function validateFileIntegrity(DectaFile $file): array
    {
        $result = [
            'exists_in_storage' => false,
            'size_matches' => false,
            'is_readable' => false,
            'storage_size' => null,
            'database_size' => $file->file_size,
            'actual_path' => null,
            'issues' => [],
        ];

        // Find actual file path
        $actualPath = $this->findActualFilePath($file);

        if ($actualPath) {
            $result['exists_in_storage'] = true;
            $result['actual_path'] = $actualPath;

            // Check file size
            $storageSize = Storage::disk($this->diskName)->size($actualPath);
            $result['storage_size'] = $storageSize;

            if ($storageSize === $file->file_size) {
                $result['size_matches'] = true;
            } else {
                $result['issues'][] = "Size mismatch: storage={$storageSize}, database={$file->file_size}";
            }

            // Check readability
            try {
                $content = Storage::disk($this->diskName)->get($actualPath);
                if ($content !== false) {
                    $result['is_readable'] = true;
                } else {
                    $result['issues'][] = 'File exists but cannot be read';
                }
            } catch (\Exception $e) {
                $result['issues'][] = "Read error: {$e->getMessage()}";
            }

            // Check if path in database is correct
            if ($actualPath !== $file->local_path) {
                $result['issues'][] = "Database path outdated: DB={$file->local_path}, Actual={$actualPath}";
            }
        } else {
            $result['issues'][] = 'File does not exist in any expected location';
            $searchedPaths = $this->getPossiblePaths($file->local_path);
            $result['issues'][] = 'Searched paths: ' . implode(', ', $searchedPaths);
        }

        return $result;
    }
}
