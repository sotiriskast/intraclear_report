<?php

namespace Modules\Decta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DectaFile extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'decta_files';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'filename',
        'original_path',
        'local_path',
        'file_size',
        'file_type',
        'status',
        'processed_at',
        'error_message',
        'metadata',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'processed_at' => 'datetime',
        'metadata' => 'json',
    ];

    /**
     * Define status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PROCESSED = 'processed';
    const STATUS_FAILED = 'failed';

    /**
     * Get the transactions that belong to this file
     */
    public function dectaTransactions(): HasMany
    {
        return $this->hasMany(DectaTransaction::class, 'decta_file_id');
    }

    /**
     * Scope a query to only include pending files.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include processing files.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope a query to only include processed files.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    /**
     * Scope a query to only include failed files.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Check if the file is processed.
     *
     * @return bool
     */
    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    /**
     * Check if the file is failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the file is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the file is processing.
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Mark the file as processing.
     *
     * @return $this
     */
    public function markAsProcessing()
    {
        $this->status = self::STATUS_PROCESSING;
        $this->save();
        return $this;
    }

    /**
     * Mark the file as processed.
     *
     * @return $this
     */
    public function markAsProcessed()
    {
        $this->status = self::STATUS_PROCESSED;
        $this->processed_at = now();
        $this->save();
        return $this;
    }

    /**
     * Mark the file as failed.
     *
     * @param string|null $errorMessage
     * @return $this
     */
    public function markAsFailed(?string $errorMessage = null)
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $errorMessage;
        $this->save();
        return $this;
    }

    /**
     * Get transaction statistics for this file
     *
     * @return array
     */
    public function getTransactionStats(): array
    {
        $total = $this->dectaTransactions()->count();
        $matched = $this->dectaTransactions()->where('is_matched', true)->count();
        $failed = $this->dectaTransactions()->where('status', DectaTransaction::STATUS_FAILED)->count();
        $pending = $this->dectaTransactions()->where('status', DectaTransaction::STATUS_PENDING)->count();

        return [
            'total' => $total,
            'matched' => $matched,
            'unmatched' => $total - $matched,
            'failed' => $failed,
            'pending' => $pending,
            'match_rate' => $total > 0 ? ($matched / $total) * 100 : 0,
        ];
    }

    /**
     * Get file size in human readable format
     *
     * @return string
     */
    public function getHumanFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->file_size;
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get processing duration in human readable format
     *
     * @return string|null
     */
    public function getProcessingDurationAttribute(): ?string
    {
        if (!$this->processed_at) {
            return null;
        }

        $duration = $this->created_at->diffInMinutes($this->processed_at);

        if ($duration < 1) {
            return 'Less than 1 minute';
        } elseif ($duration < 60) {
            return $duration . ' minute' . ($duration > 1 ? 's' : '');
        } else {
            $hours = floor($duration / 60);
            $minutes = $duration % 60;
            return $hours . ' hour' . ($hours > 1 ? 's' : '') .
                ($minutes > 0 ? ' ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '') : '');
        }
    }

    /**
     * Get the file's target date from metadata
     *
     * @return string|null
     */
    public function getTargetDateAttribute(): ?string
    {
        return $this->metadata['target_date'] ?? null;
    }

    /**
     * Get the file's download date from metadata
     *
     * @return string|null
     */
    public function getDownloadDateAttribute(): ?string
    {
        return $this->metadata['download_date'] ?? null;
    }
}
