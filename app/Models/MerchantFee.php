<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantFee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'fee_type_id',
        'amount',
        'effective_from',
        'effective_to',
        'active',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'active' => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
