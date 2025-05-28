<?php

namespace Modules\Decta\Repositories;

use Modules\Decta\Models\DectaTransaction;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class DectaTransactionRepository
{
    /**
     * Create a new transaction record
     *
     * @param array $data Transaction data
     * @return DectaTransaction
     */
    public function create(array $data): DectaTransaction
    {
        return DectaTransaction::create($data);
    }

    /**
     * Find transaction by ID
     *
     * @param int $id
     * @return DectaTransaction|null
     */
    public function findById(int $id): ?DectaTransaction
    {
        return DectaTransaction::find($id);
    }

    /**
     * Find transaction by payment ID
     *
     * @param string $paymentId
     * @return DectaTransaction|null
     */
    public function findByPaymentId(string $paymentId): ?DectaTransaction
    {
        return DectaTransaction::where('payment_id', $paymentId)->first();
    }

    /**
     * Get transactions for a specific file
     *
     * @param int $fileId
     * @return Collection
     */
    public function getByFileId(int $fileId): Collection
    {
        return DectaTransaction::where('decta_file_id', $fileId)->get();
    }

    /**
     * Get unmatched transactions
     *
     * @param int|null $fileId Optional file ID filter
     * @param int|null $limit Optional limit
     * @return Collection
     */
    public function getUnmatched(?int $fileId = null, ?int $limit = null): Collection
    {
        $query = DectaTransaction::unmatched();

        if ($fileId) {
            $query->where('decta_file_id', $fileId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get matched transactions
     *
     * @param int|null $fileId Optional file ID filter
     * @param int|null $limit Optional limit
     * @return Collection
     */
    public function getMatched(?int $fileId = null, ?int $limit = null): Collection
    {
        $query = DectaTransaction::matched();

        if ($fileId) {
            $query->where('decta_file_id', $fileId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get failed transactions
     *
     * @param int|null $fileId Optional file ID filter
     * @return Collection
     */
    public function getFailed(?int $fileId = null): Collection
    {
        $query = DectaTransaction::failed();

        if ($fileId) {
            $query->where('decta_file_id', $fileId);
        }

        return $query->get();
    }

    /**
     * Get transactions with pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = DectaTransaction::query()->with('dectaFile');

        // Apply filters
        if (isset($filters['file_id'])) {
            $query->where('decta_file_id', $filters['file_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_matched'])) {
            $query->where('is_matched', $filters['is_matched']);
        }

        if (isset($filters['payment_id'])) {
            $query->where('payment_id', 'like', '%' . $filters['payment_id'] . '%');
        }

        if (isset($filters['merchant_id'])) {
            $query->where('merchant_id', $filters['merchant_id']);
        }

        if (isset($filters['currency'])) {
            $query->where('tr_ccy', $filters['currency']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('tr_date_time', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('tr_date_time', '<=', $filters['date_to']);
        }

        if (isset($filters['amount_from'])) {
            $query->where('tr_amount', '>=', $filters['amount_from'] * 100);
        }

        if (isset($filters['amount_to'])) {
            $query->where('tr_amount', '<=', $filters['amount_to'] * 100);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get transaction statistics
     *
     * @param int|null $fileId Optional file ID filter
     * @param array $dateRange Optional date range filter
     * @return array
     */
    public function getStatistics(?int $fileId = null, array $dateRange = []): array
    {
        $query = DectaTransaction::query();

        if ($fileId) {
            $query->where('decta_file_id', $fileId);
        }

        if (isset($dateRange['start'])) {
            $query->whereDate('tr_date_time', '>=', $dateRange['start']);
        }

        if (isset($dateRange['end'])) {
            $query->whereDate('tr_date_time', '<=', $dateRange['end']);
        }

        $total = $query->count();
        $matched = (clone $query)->where('is_matched', true)->count();
        $unmatched = (clone $query)->where('is_matched', false)->count();
        $failed = (clone $query)->where('status', DectaTransaction::STATUS_FAILED)->count();
        $pending = (clone $query)->where('status', DectaTransaction::STATUS_PENDING)->count();

        // Get amount statistics
        $totalAmount = (clone $query)->sum('tr_amount') / 100;
        $matchedAmount = (clone $query)->where('is_matched', true)->sum('tr_amount') / 100;

        // Get currency breakdown
        $currencyStats = (clone $query)
            ->selectRaw('tr_ccy, COUNT(*) as count, SUM(tr_amount) as total_amount')
            ->whereNotNull('tr_ccy')
            ->groupBy('tr_ccy')
            ->get()
            ->map(function ($item) {
                return [
                    'currency' => $item->tr_ccy,
                    'count' => $item->count,
                    'total_amount' => $item->total_amount / 100,
                ];
            })
            ->keyBy('currency')
            ->toArray();

        // Get daily statistics for the last 30 days
        $dailyStats = (clone $query)
            ->selectRaw('DATE(tr_date_time) as date, COUNT(*) as count, SUM(tr_amount) as total_amount')
            ->where('tr_date_time', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                    'total_amount' => $item->total_amount / 100,
                ];
            })
            ->toArray();

        return [
            'total' => $total,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'failed' => $failed,
            'pending' => $pending,
            'match_rate' => $total > 0 ? ($matched / $total) * 100 : 0,
            'total_amount' => $totalAmount,
            'matched_amount' => $matchedAmount,
            'currency_breakdown' => $currencyStats,
            'daily_stats' => $dailyStats,
        ];
    }

    /**
     * Bulk update transaction statuses
     *
     * @param array $ids
     * @param string $status
     * @param array $additionalData
     * @return int Number of updated records
     */
    public function bulkUpdateStatus(array $ids, string $status, array $additionalData = []): int
    {
        $updateData = array_merge(['status' => $status], $additionalData);

        return DectaTransaction::whereIn('id', $ids)->update($updateData);
    }

    /**
     * Delete transactions for a specific file
     *
     * @param int $fileId
     * @return int Number of deleted records
     */
    public function deleteByFileId(int $fileId): int
    {
        return DectaTransaction::where('decta_file_id', $fileId)->delete();
    }

    /**
     * Get transactions that need re-matching
     *
     * @param int $maxAttempts Maximum number of matching attempts
     * @return Collection
     */
    public function getForReMatching(int $maxAttempts = 3): Collection
    {
        return DectaTransaction::where('is_matched', false)
            ->where('status', '!=', DectaTransaction::STATUS_FAILED)
            ->whereRaw('JSON_LENGTH(COALESCE(matching_attempts, "[]")) < ?', [$maxAttempts])
            ->get();
    }

    /**
     * Find duplicate transactions based on payment_id
     *
     * @return Collection
     */
    public function findDuplicates(): Collection
    {
        $duplicatePaymentIds = DectaTransaction::selectRaw('payment_id, COUNT(*) as count')
            ->groupBy('payment_id')
            ->having('count', '>', 1)
            ->pluck('payment_id');

        return DectaTransaction::whereIn('payment_id', $duplicatePaymentIds)
            ->orderBy('payment_id')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Clean up old unmatched transactions
     *
     * @param int $daysOld
     * @return int Number of deleted records
     */
    public function cleanupOldUnmatched(int $daysOld = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);

        return DectaTransaction::where('is_matched', false)
            ->where('status', DectaTransaction::STATUS_FAILED)
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}
