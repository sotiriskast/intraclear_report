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
        'name',
        'legal_name',
        'register_country',
        'city',
        'street',
        'postcode',
        'vat',
        'mcc1',
        'mcc2',
        'mcc3',
        'iso_country_code'
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

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->api_key;
    }

    /**
     * Get the remember token for the user.
     *
     * @return string|null
     */
    public function getRememberToken()
    {
        return null; // Not using remember tokens for API
    }
}
