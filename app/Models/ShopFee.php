<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopFee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shop_id',
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

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
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
