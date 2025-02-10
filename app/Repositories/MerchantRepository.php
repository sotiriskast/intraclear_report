<?php

namespace App\Repositories;

use App\Models\Merchant;

/**
 * Repository for managing merchant data operations
 * Handles merchant retrieval and filtering operations
 */
class MerchantRepository
{
    /**
     * Get internal merchant ID using external account ID
     *
     * @param  int|string  $accountId  External account identifier
     * @return int Internal merchant ID
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If merchant not found
     */
    public function getMerchantIdByAccountId(int|string $accountId): int
    {
        return Merchant::query()->where('account_id', $accountId)->first()->id;
    }

    public function getActive()
    {
        return Merchant::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    // If you need with pagination
    public function getActiveWithPagination($perPage = 10)
    {
        return Merchant::query()
            ->active()
            ->orderBy('name')
            ->paginate($perPage);
    }
}
