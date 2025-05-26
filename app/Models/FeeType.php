<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'key',
        'frequency_type',
        'is_percentage',
    ];

    public function shopFees(): HasMany
    {
        return $this->hasMany(ShopFee::class);
    }
    public function feeHistory(): HasMany
    {
        return $this->hasMany(FeeHistory::class);
    }
}
