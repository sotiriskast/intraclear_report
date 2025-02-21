<?php
namespace App\Api\V1\Services\RollingReserve;

use App\Api\V1\Filters\RollingReserve\RollingReserveFilter;
use App\Api\V1\Services\RollingReserve\Responses\ApiResponse;
use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use App\Services\DynamicLogger;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

readonly class RollingReserveApiService
{
    public function __construct(
        private RollingReserveRepositoryInterface $repository,
        private DynamicLogger                     $logger
    ) {}

    public function listReserves(int $merchantId, array $filters = [], int $perPage = 15): ApiResponse
    {
        try {
            $query = $this->repository->getRollingReserves($merchantId);

            // Apply filters - now passing the logger
            $filterQuery = new RollingReserveFilter($query, $this->logger);
            $query = $filterQuery->apply($filters);

            $reserves = $query->paginate($perPage);

            return ApiResponse::success(
                data: $reserves->items(),
                meta: [
                    'current_page' => $reserves->currentPage(),
                    'per_page' => $reserves->perPage(),
                    'total' => $reserves->total(),
                    'last_page' => $reserves->lastPage()
                ]
            );

        } catch (QueryException $e) {
            $this->logger->log('error', 'Database error while retrieving rolling reserves', [
                'merchant_id' => $merchantId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::failure('Database error occurred');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to retrieve rolling reserves', [
                'merchant_id' => $merchantId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::failure('An unexpected error occurred');
        }
    }
    public function getReserveSummary(int $merchantId, ?string $currency = null): ApiResponse
    {
        try {
            $summary = $this->repository->getReserveSummary($merchantId, $currency);
            return ApiResponse::success($summary);
        } catch (\Exception $e) {
            $this->logger->log('error', 'API: Failed to retrieve reserve summary', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::failure('Failed to retrieve reserve summary');
        }
    }
}

