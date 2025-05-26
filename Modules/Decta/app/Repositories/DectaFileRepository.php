<?php

namespace Modules\Decta\Repositories;

use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class DectaFileRepository
{
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
     * Get file content from storage
     *
     * @param DectaFile $file
     * @return string|null
     */
    public function getFileContent(DectaFile $file): ?string
    {
        if (Storage::disk('local')->exists($file->local_path)) {
            return Storage::disk('local')->get($file->local_path);
        }

        return null;
    }

    /**
     * Delete file from storage
     *
     * @param DectaFile $file
     * @return bool
     */
    public function deleteFile(DectaFile $file): bool
    {
        if (Storage::disk('local')->exists($file->local_path)) {
            Storage::disk('local')->delete($file->local_path);
        }

        return $file->delete();
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

        return [
            'total' => $totalFiles,
            'processed' => $processedFiles,
            'failed' => $failedFiles,
            'pending' => $pendingFiles,
            'success_rate' => $totalFiles > 0 ? ($processedFiles / $totalFiles) * 100 : 0,
            'by_file_type' => $byFileType,
        ];
    }
}
