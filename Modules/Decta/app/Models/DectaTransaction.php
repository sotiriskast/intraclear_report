<?php

namespace Modules\Decta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class DectaTransaction extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'decta_transactions';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_MATCHED = 'matched';
    const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'decta_file_id',
        'payment_id',
        'card',
        'merchant_name',
        'merchant_id',
        'terminal_id',
        'card_type_name',
        'acq_ref_nr',
        'tr_batch_id',
        'tr_batch_open_date',
        'tr_date_time',
        'tr_type',
        'tr_amount',
        'tr_ccy',
        'msc',
        'tr_ret_ref_nr',
        'tr_approval_id',
        'tr_processing_date',
        'merchant_iban_code',
        'proc_code',
        'issuer_country',
        'proc_region',
        'mcc',
        'merchant_country',
        'tran_region',
        'card_product_type',
        'user_define_field1',
        'user_define_field2',
        'user_define_field3',
        'merchant_legal_name',
        'card_product_class',
        'eci_sli',
        'sca_exemption',
        'point_code',
        'pos_env_indicator',
        'par',
        'gateway_transaction_id',
        'gateway_account_id',
        'gateway_shop_id',
        'gateway_trx_id',
        'gateway_transaction_status',
        'gateway_bank_response_date',
        'gateway_transaction_date',
        'gateway_trx_id',
        'is_matched',
        'matched_at',
        'status',
        'error_message',
        'matching_attempts',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tr_batch_open_date' => 'datetime',
        'tr_date_time' => 'datetime',
        'tr_processing_date' => 'datetime',
        'matched_at' => 'datetime',
        'is_matched' => 'boolean',
        'matching_attempts' => 'json',
        'tr_amount' => 'integer',
        'gateway_bank_response_date' => 'datetime',
        'gateway_transaction_date' => 'datetime',
    ];

    /**
     * Get the file that this transaction belongs to
     */
    public function dectaFile(): BelongsTo
    {
        return $this->belongsTo(DectaFile::class, 'decta_file_id');
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for matched transactions
     */
    public function scopeMatched($query)
    {
        return $query->where('status', self::STATUS_MATCHED);
    }

    /**
     * Scope for unmatched transactions
     */
    public function scopeUnmatched($query)
    {
        return $query->where('is_matched', false);
    }

    /**
     * Scope for failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Mark transaction as matched with gateway data
     */
    public function markAsMatched(array $gatewayData): self
    {
        $this->update([
            // Gateway transaction identifiers
            'gateway_transaction_id' => $gatewayData['transaction_id'] ?? null,
            'gateway_account_id' => $gatewayData['account_id'] ?? null,
            'gateway_shop_id' => $gatewayData['shop_id'] ?? null,
            'gateway_trx_id' => $gatewayData['trx_id'] ?? null,
            'gateway_transaction_status' => $gatewayData['transaction_status'] ?? null,

            // Gateway transaction dates
            'gateway_transaction_date' => $gatewayData['transaction_date'] ?? null,
            'gateway_bank_response_date' => $gatewayData['bank_response_date'] ?? null,

            // Matching status
            'is_matched' => true,
            'matched_at' => Carbon::now(),
            'status' => self::STATUS_MATCHED,
            'error_message' => null, // Clear any previous error
        ]);

        return $this;
    }

    /**
     * Mark transaction as failed
     */
    public function markAsFailed(string $errorMessage, array $attempts = []): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'matching_attempts' => $attempts,
        ]);

        return $this;
    }

    /**
     * Mark transaction as processing
     */
    public function markAsProcessing(): self
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
        ]);

        return $this;
    }

    /**
     * Add matching attempt
     */
    public function addMatchingAttempt(array $attemptData): self
    {
        $attempts = $this->matching_attempts ?? [];
        $attempts[] = array_merge($attemptData, [
            'attempted_at' => Carbon::now()->toISOString(),
        ]);

        $this->update([
            'matching_attempts' => $attempts,
        ]);

        return $this;
    }

    /**
     * Get transaction amount in base currency (from cents)
     */
    public function getAmountAttribute(): float
    {
        return $this->tr_amount ? $this->tr_amount / 100 : 0;
    }

    /**
     * Set transaction amount (convert to cents)
     */
    public function setAmountAttribute(float $value): void
    {
        $this->attributes['tr_amount'] = (int) round($value * 100);
    }

    /**
     * Check if transaction has been matched
     */
    public function isMatched(): bool
    {
        return $this->is_matched && $this->status === self::STATUS_MATCHED;
    }

    /**
     * Check if transaction is pending processing
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction processing failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get matching statistics for a file
     */
    public static function getMatchingStats(int $fileId): array
    {
        $total = static::where('decta_file_id', $fileId)->count();
        $matched = static::where('decta_file_id', $fileId)->matched()->count();
        $failed = static::where('decta_file_id', $fileId)->failed()->count();
        $pending = static::where('decta_file_id', $fileId)->pending()->count();

        return [
            'total' => $total,
            'matched' => $matched,
            'failed' => $failed,
            'pending' => $pending,
            'match_rate' => $total > 0 ? ($matched / $total) * 100 : 0,
        ];
    }

    /**
     * Validate that gateway data was stored correctly
     *
     * @return array Validation results
     */
    public function validateGatewayData(): array
    {
        $validation = [
            'is_valid' => true,
            'has_required_fields' => true,
            'missing_fields' => [],
            'issues' => [],
        ];

        if (!$this->is_matched) {
            $validation['is_valid'] = false;
            $validation['issues'][] = 'Transaction not marked as matched';
            return $validation;
        }

        // Check required gateway fields
        $requiredFields = [
            'gateway_transaction_id' => 'Gateway Transaction ID',
            'gateway_account_id' => 'Gateway Account ID',
            'gateway_shop_id' => 'Gateway Shop ID',
            'gateway_trx_id' => 'Gateway TRX ID',
        ];

        foreach ($requiredFields as $field => $label) {
            if (empty($this->$field)) {
                $validation['has_required_fields'] = false;
                $validation['missing_fields'][] = $label;
                $validation['issues'][] = "Missing {$label}";
            }
        }

        // Check if matched_at timestamp is set
        if (!$this->matched_at) {
            $validation['issues'][] = 'Missing matched_at timestamp';
        }

        // Check if status is correct
        if ($this->status !== self::STATUS_MATCHED) {
            $validation['issues'][] = "Status should be 'matched' but is '{$this->status}'";
        }

        $validation['is_valid'] = empty($validation['issues']);

        return $validation;
    }

    /**
     * Get gateway transaction summary for display
     *
     * @return array
     */
    public function getGatewaySummary(): array
    {
        if (!$this->is_matched) {
            return [
                'matched' => false,
                'message' => 'Transaction not matched with gateway'
            ];
        }

        return [
            'matched' => true,
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'gateway_account_id' => $this->gateway_account_id,
            'gateway_shop_id' => $this->gateway_shop_id,
            'gateway_trx_id' => $this->gateway_trx_id,
            'gateway_transaction_date' => $this->gateway_transaction_date,
            'gateway_bank_response_date' => $this->gateway_bank_response_date,
            'matched_at' => $this->matched_at,
            'validation' => $this->validateGatewayData()
        ];
    }
}
