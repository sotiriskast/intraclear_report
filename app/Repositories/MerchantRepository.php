<?php

namespace App\Repositories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository for managing merchant data operations
 * Handles merchant retrieval and filtering operations
 */
class MerchantRepository
{
    /**
     * @var Merchant
     */
    protected $model;

    /**
     * MerchantRepository constructor.
     */
    public function __construct()
    {
        $this->model = new Merchant();
    }
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
    public function findById(int $id): ?Merchant
    {
        return $this->model->find($id);
    }

    public function findByAccountId(int $accountId): ?Merchant
    {
        return $this->model->where('account_id', $accountId)->first();
    }

    public function getRollingReserves(Merchant $merchant, array $filters = []): Collection
    {
        $query = $merchant->rollingReserves();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date'])) {
            $query->where('period_start', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('period_end', '<=', $filters['end_date']);
        }

        if (isset($filters['currency'])) {
            $query->where('original_currency', $filters['currency']);
        }

        return $query->get();
    }
}
