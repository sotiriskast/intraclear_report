<?php

namespace Modules\Decta\Models;

use Illuminate\Database\Eloquent\Model;
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
}
