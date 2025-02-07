<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Carbon\Carbon;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * ChargebackTracking Model
 *
 * @property int $id
 * @property int $merchant_id
 * @property string $transaction_id
 * @property float $amount
 * @property string $currency
 * @property float $amount_eur
 * @property float $exchange_rate
 * @property string $initial_status
 * @property string $current_status
 * @property Carbon $processing_date
 * @property Carbon|null $status_changed_date
 * @property bool $settled
 * @property Carbon|null $settled_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class ChargebackTracking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'transaction_id',
        'amount',
        'currency',
        'amount_eur',
        'exchange_rate',
        'initial_status',
        'current_status',
        'processing_date',
        'status_changed_date',
        'settled',
        'settled_date'
    ];

    protected $casts = [
        'processing_date' => 'datetime',
        'status_changed_date' => 'datetime',
        'settled_date' => 'datetime',
        'settled' => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
