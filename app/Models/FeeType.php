<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    public function merchantFees()
    {
        return $this->hasMany(MerchantFee::class);
    }
}
