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
    )
    {
    }

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

    public function getReserve(int $merchantId, int $reserveId): ApiResponse
    {
        try {
            $query = $this->repository->getRollingReserves($merchantId);
            $reserve = $query->where('id', $reserveId)->first();

            if (!$reserve) {
                $this->logger->log('warning', 'Reserve not found', [
                    'merchant_id' => $merchantId,
                    'reserve_id' => $reserveId
                ]);
                return ApiResponse::failure('Reserve not found');
            }

            return ApiResponse::success($reserve);

        } catch (QueryException $e) {
            $this->logger->log('error', 'Database error while retrieving rolling reserve', [
                'merchant_id' => $merchantId,
                'reserve_id' => $reserveId,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::failure('Database error occurred');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to retrieve rolling reserve', [
                'merchant_id' => $merchantId,
                'reserve_id' => $reserveId,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::failure('An unexpected error occurred');
        }
    }
}

