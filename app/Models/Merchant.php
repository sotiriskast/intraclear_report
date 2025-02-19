<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Merchant extends Model
{
    use HasApiTokens;
    protected $fillable = [
        'account_id',
        'email',
        'phone',
        'active',
        'api_key',
    ];

    protected $casts = [
        'active' => 'boolean',
        'api_key' => 'hashed',
    ];
    protected $hidden = [
        'api_key',
    ];
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function merchantFees(): HasMany
    {
        return $this->hasMany(MerchantFee::class);
    }
    public function rollingReserves(): HasMany
    {
        return $this->hasMany(RollingReserveEntry::class);
    }

}
