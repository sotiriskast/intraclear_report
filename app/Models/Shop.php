<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    protected $fillable = [
        'shop_id',
        'merchant_id',
        'email',
        'website',
        'owner_name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(ShopSetting::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(ShopFee::class);
    }

    public function rollingReserves(): HasMany
    {
        return $this->hasMany(RollingReserveEntry::class);
    }

    public function feeHistories(): HasMany
    {
        return $this->hasMany(FeeHistory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
