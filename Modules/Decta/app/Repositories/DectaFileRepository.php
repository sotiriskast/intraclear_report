<?php

namespace Modules\Decta\Repositories;

use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        return DectaFile::pending()->get();
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
     * Get file content from storage using the decta disk
     *
     * @param DectaFile $file
     * @return string|null
     */
    public function getFileContent(DectaFile $file): ?string
    {
        if (Storage::disk($this->diskName)->exists($file->local_path)) {
            return Storage::disk($this->diskName)->get($file->local_path);
        }

        return null;
    }

    /**
     * Delete file from storage using the decta disk
     *
     * @param DectaFile $file
     * @return bool
     */
    public function deleteFile(DectaFile $file): bool
    {
        if (Storage::disk($this->diskName)->exists($file->local_path)) {
            Storage::disk($this->diskName)->delete($file->local_path);
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
        return Storage::disk($this->diskName)->exists($file->local_path);
    }

    /**
     * Get file size from storage using the decta disk
     *
     * @param DectaFile $file
     * @return int|false
     */
    public function getFileSize(DectaFile $file): int|false
    {
        if (Storage::disk($this->diskName)->exists($file->local_path)) {
            return Storage::disk($this->diskName)->size($file->local_path);
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
        if (Storage::disk($this->diskName)->exists($file->local_path)) {
            return Storage::disk($this->diskName)->lastModified($file->local_path);
        }

        return false;
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
            'issues' => [],
        ];

        // Check if file exists
        if (Storage::disk($this->diskName)->exists($file->local_path)) {
            $result['exists_in_storage'] = true;

            // Check file size
            $storageSize = Storage::disk($this->diskName)->size($file->local_path);
            $result['storage_size'] = $storageSize;

            if ($storageSize === $file->file_size) {
                $result['size_matches'] = true;
            } else {
                $result['issues'][] = "Size mismatch: storage={$storageSize}, database={$file->file_size}";
            }

            // Check readability
            try {
                $content = Storage::disk($this->diskName)->get($file->local_path);
                if ($content !== false) {
                    $result['is_readable'] = true;
                } else {
                    $result['issues'][] = 'File exists but cannot be read';
                }
            } catch (\Exception $e) {
                $result['issues'][] = "Read error: {$e->getMessage()}";
            }
        } else {
            $result['issues'][] = 'File does not exist in storage';
        }

        return $result;
    }
}
