<?php

namespace App\Repositories;

use App\Models\FeeType;
use App\Models\Merchant;
use App\Models\MerchantFee;
use App\Models\FeeHistory;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use Illuminate\Support\Facades\DB;
/**
 * Repository for managing merchant data operations
 * Handles merchant retrieval and filtering operations
 */
class MerchantRepository
{
    /**
     * Get internal merchant ID using external account ID
     *
     * @param int|string $accountId External account identifier
     * @return int Internal merchant ID
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If merchant not found
     */
    public function getMerchantIdByAccountId(int|string $accountId): int
    {
        return Merchant::query()->where('account_id', $accountId)->first()->id;
    }
}
