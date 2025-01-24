<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{

    protected $fillable = [
        'account_id',
        'email',
        'phone',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function merchantFees()
    {
        return $this->hasMany(MerchantFee::class);
    }
}
