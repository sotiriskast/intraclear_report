<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\RollingReserveEntryObserver;

#[ObservedBy([RollingReserveEntryObserver::class])]
class RollingReserveEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'original_amount',
        'original_currency',
        'reserve_amount_eur',
        'exchange_rate',
        'period_start',
        'period_end',
        'release_due_date',
        'released_at',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'release_due_date' => 'date',
        'released_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'original_amount' => 'integer',
        'reserve_amount_eur' => 'integer',
        'exchange_rate' => 'integer',
    ];
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
