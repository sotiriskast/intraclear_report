<?php

namespace App\Livewire;

use App\Models\Merchant;
use App\Models\FeeHistory;
use App\Models\RollingReserveEntry;
use App\Models\ChargebackTracking;
use App\Repositories\FeeRepository;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use App\Repositories\RollingReserveRepository;
use App\Repositories\ChargebackTrackingRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app', ['header' => 'Merchant Analytics'])]
#[Title('Merchant Analytics')]
class MerchantAnalytics extends Component
{
    public $merchant;
    public $period = 'last30days';
    public $dateRange = [];
    public $isLoading = true; // Start with loading state true

    // Dependencies
    protected $feeRepository;
    protected $reserveRepository;
    protected $chargebackRepository;
    protected $transactionRepository;

    // Cache duration
    protected const CACHE_DURATION = 60; // 60 minutes

    protected function getListeners()
    {
        return [
            'periodUpdated' => 'updatedPeriod',
            'refreshData' => '$refresh',
        ];
    }

    public function boot(
        FeeRepository $feeRepository,
        RollingReserveRepository $reserveRepository,
        ChargebackTrackingRepository $chargebackRepository,
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->feeRepository = $feeRepository;
        $this->reserveRepository = $reserveRepository;
        $this->chargebackRepository = $chargebackRepository;
        $this->transactionRepository = $transactionRepository;
    }

    public function mount(Merchant $merchant)
    {
        $this->merchant = $merchant;
        $this->setDateRange('last30days');
    }

    public function updatedPeriod($value)
    {
        $this->isLoading = true;
        $this->setDateRange($value);
    }

    /**
     * Set the date range based on selected period
     */
    public function setDateRange($period)
    {
        $this->period = $period;

        switch ($period) {
            case 'last7days':
                $this->dateRange = [
                    'start' => Carbon::now()->subDays(7)->startOfDay(),
                    'end' => Carbon::now()->endOfDay()
                ];
                break;
            case 'last30days':
                $this->dateRange = [
                    'start' => Carbon::now()->subDays(30)->startOfDay(),
                    'end' => Carbon::now()->endOfDay()
                ];
                break;
            case 'last90days':
                $this->dateRange = [
                    'start' => Carbon::now()->subDays(90)->startOfDay(),
                    'end' => Carbon::now()->endOfDay()
                ];
                break;
            case 'thisyear':
                $this->dateRange = [
                    'start' => Carbon::now()->startOfYear(),
                    'end' => Carbon::now()->endOfDay()
                ];
                break;
            case 'lastyear':
                $this->dateRange = [
                    'start' => Carbon::now()->subYear()->startOfYear(),
                    'end' => Carbon::now()->subYear()->endOfYear()
                ];
                break;
            default:
                $this->dateRange = [
                    'start' => Carbon::now()->subDays(30)->startOfDay(),
                    'end' => Carbon::now()->endOfDay()
                ];
        }
    }

    /**
     * Get transaction metrics with caching
     */
    #[Computed]
    public function getTransactionMetricsProperty()
    {
        $cacheKey = "merchant-{$this->merchant->id}-transactions-{$this->period}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_DURATION), function () {
            try {
                // Try to get data from TransactionRepository if available
                if (isset($this->transactionRepository)) {
                    try {
                        // Get transactions within date range
                        $transactions = $this->transactionRepository->getMerchantTransactions(
                            $this->merchant->account_id,
                            [
                                'start' => $this->dateRange['start']->format('Y-m-d'),
                                'end' => $this->dateRange['end']->format('Y-m-d')
                            ]
                        );

                        if ($transactions && $transactions->isNotEmpty()) {
                            // Get exchange rates
                            $currencies = $transactions->pluck('currency')->unique()->toArray();
                            $exchangeRates = $this->transactionRepository->getExchangeRates(
                                [
                                    'start' => $this->dateRange['start']->format('Y-m-d'),
                                    'end' => $this->dateRange['end']->format('Y-m-d')
                                ],
                                $currencies
                            );

                            // Calculate transaction totals
                            $totals = $this->transactionRepository->calculateTransactionTotals($transactions, $exchangeRates, $this->merchant->account_id);

                            // Combine totals from all currencies
                            $summary = [
                                'total_sales' => 0,
                                'total_sales_eur' => 0,
                                'transaction_count' => 0,
                                'declined_count' => 0,
                                'refund_count' => 0,
                                'chargeback_count' => 0,
                                'payout_count' => 0
                            ];

                            foreach ($totals as $currencyTotals) {
                                $summary['total_sales'] += $currencyTotals['total_sales'] ?? 0;
                                $summary['total_sales_eur'] += $currencyTotals['total_sales_eur'] ?? 0;
                                $summary['transaction_count'] += $currencyTotals['transaction_sales_count'] ?? 0;
                                $summary['declined_count'] += $currencyTotals['transaction_declined_count'] ?? 0;
                                $summary['refund_count'] += $currencyTotals['transaction_refunds_count'] ?? 0;
                                $summary['chargeback_count'] += $currencyTotals['total_chargeback_count'] ?? 0;
                                $summary['payout_count'] += $currencyTotals['total_payout_count'] ?? 0;
                            }

                            return $summary;
                        }
                    } catch (\Exception $e) {
                        logger()->error('Error fetching transaction metrics from repository', [
                            'merchant_id' => $this->merchant->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Fallback: compute metrics from fee history
                $feeMetrics = $this->getFeeMetricsProperty();
                $transactionFees = collect($feeMetrics['fees_by_type'])->filter(function ($fee) {
                    return stripos($fee['name'], 'transaction') !== false;
                })->first();

                if ($transactionFees && isset($transactionFees['count']) && $transactionFees['count'] > 0) {
                    return [
                        'total_sales' => 0,
                        'total_sales_eur' => 0,
                        'transaction_count' => $transactionFees['count'],
                        'declined_count' => 0,
                        'refund_count' => 0,
                        'chargeback_count' => 0,
                        'payout_count' => 0,
                        'estimated' => true
                    ];
                }

                // Nothing worked, return zeros
                return [
                    'total_sales' => 0,
                    'total_sales_eur' => 0,
                    'transaction_count' => 0,
                    'declined_count' => 0,
                    'refund_count' => 0,
                    'chargeback_count' => 0,
                    'payout_count' => 0
                ];

            } catch (\Exception $e) {
                logger()->error('Error fetching transaction metrics', [
                    'merchant_id' => $this->merchant->id,
                    'error' => $e->getMessage()
                ]);

                return [
                    'total_sales' => 0,
                    'total_sales_eur' => 0,
                    'transaction_count' => 0,
                    'declined_count' => 0,
                    'refund_count' => 0,
                    'chargeback_count' => 0,
                    'payout_count' => 0,
                    'error' => true
                ];
            }
        });
    }

    /**
     * Get fee metrics with caching
     */
    #[Computed]
    public function getFeeMetricsProperty()
    {
        $cacheKey = "merchant-{$this->merchant->id}-fees-{$this->period}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_DURATION), function () {
            try {
                // Get fees for the selected period
                $feeHistory = FeeHistory::query()
                    ->select(['id', 'fee_type_id', 'fee_amount_eur', 'applied_date'])
                    ->where('merchant_id', $this->merchant->id)
                    ->whereBetween('applied_date', [
                        $this->dateRange['start']->format('Y-m-d'),
                        $this->dateRange['end']->format('Y-m-d')
                    ])
                    ->with(['feeType:id,name']) // Eager load only needed fields
                    ->get();

                // Calculate period fee metrics
                $totalFees = $feeHistory->sum('fee_amount_eur') / 100;

                // Get fees by type (optimized)
                $feesByType = $feeHistory->groupBy('fee_type_id')
                    ->map(function ($feeGroup, $feeTypeId) {
                        $feeName = $feeGroup->first()->feeType->name ?? "Fee Type $feeTypeId";
                        return [
                            'name' => $feeName,
                            'amount' => $feeGroup->sum('fee_amount_eur') / 100,
                            'count' => $feeGroup->count()
                        ];
                    })
                    ->sortByDesc('amount')
                    ->values()
                    ->toArray();

                // Get monthly fee trend (optimized query)
                $monthlyTrend = DB::table('fee_histories')
                    ->select([
                        DB::raw(DB::connection()->getDriverName() === 'pgsql' 
                            ? 'TO_CHAR(applied_date, \'YYYY-MM\') as month_year'
                            : 'DATE_FORMAT(applied_date, "%Y-%m") as month_year'),
                        DB::raw('SUM(fee_amount_eur) / 100 as amount'),
                        DB::raw('COUNT(*) as count')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->where('applied_date', '>=', Carbon::now()->subMonths(12))
                    ->groupBy('month_year')
                    ->orderBy('month_year')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'month' => Carbon::createFromFormat('Y-m', $item->month_year)->format('M Y'),
                            'amount' => $item->amount,
                            'count' => $item->count
                        ];
                    })
                    ->toArray();

                return [
                    'total_fees' => $totalFees,
                    'fees_by_type' => $feesByType,
                    'fee_count' => $feeHistory->count(),
                    'monthly_trend' => $monthlyTrend
                ];
            } catch (\Exception $e) {
                logger()->error('Error fetching fee metrics', [
                    'merchant_id' => $this->merchant->id,
                    'error' => $e->getMessage()
                ]);

                return [
                    'total_fees' => 0,
                    'fees_by_type' => [],
                    'fee_count' => 0,
                    'monthly_trend' => [],
                    'error' => true
                ];
            }
        });
    }

    /**
     * Get rolling reserve metrics with caching
     */
    #[Computed]
    public function getRollingReserveMetricsProperty()
    {
        $cacheKey = "merchant-{$this->merchant->id}-reserves";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_DURATION), function () {
            try {
                // Get rolling reserve summary
                $reserveSummary = DB::table('rolling_reserve_entries')
                    ->select([
                        DB::raw('SUM(CASE WHEN status = "pending" THEN reserve_amount_eur ELSE 0 END) / 100 as total_reserved_eur'),
                        DB::raw('COUNT(CASE WHEN status = "pending" THEN 1 ELSE NULL END) as pending_count'),
                        DB::raw('COUNT(CASE WHEN status = "released" THEN 1 ELSE NULL END) as released_count')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->first();

                // Get upcoming releases - next 30 days
                $upcomingReleases = DB::table('rolling_reserve_entries')
                    ->select([
                        'release_due_date',
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(reserve_amount_eur) / 100 as amount_eur')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->where('status', 'pending')
                    ->where('release_due_date', '>=', now())
                    ->where('release_due_date', '<=', now()->addDays(30))
                    ->groupBy('release_due_date')
                    ->orderBy('release_due_date')
                    ->limit(8)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'release_date' => Carbon::parse($item->release_due_date),
                            'count' => $item->count,
                            'amount_eur' => $item->amount_eur
                        ];
                    })
                    ->toArray();

                // Get future releases by month (next 12 months)
                $futureReleases = DB::table('rolling_reserve_entries')
                    ->select([
                        DB::raw(DB::connection()->getDriverName() === 'pgsql' 
                            ? 'TO_CHAR(release_due_date, \'YYYY-MM\') as month_year'
                            : 'DATE_FORMAT(release_due_date, "%Y-%m") as month_year'),
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(reserve_amount_eur) / 100 as amount_eur')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->where('status', 'pending')
                    ->where('release_due_date', '>=', now())
                    ->where('release_due_date', '<=', now()->addMonths(12))
                    ->groupBy('month_year')
                    ->orderBy('month_year')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'month' => Carbon::createFromFormat('Y-m', $item->month_year)->format('M Y'),
                            'count' => $item->count,
                            'amount_eur' => $item->amount_eur
                        ];
                    })
                    ->toArray();

                return [
                    'total_reserved_eur' => $reserveSummary->total_reserved_eur ?? 0,
                    'pending_count' => $reserveSummary->pending_count ?? 0,
                    'released_count' => $reserveSummary->released_count ?? 0,
                    'upcoming_releases' => $upcomingReleases,
                    'future_releases' => $futureReleases
                ];
            } catch (\Exception $e) {
                logger()->error('Error fetching rolling reserve metrics', [
                    'merchant_id' => $this->merchant->id,
                    'error' => $e->getMessage()
                ]);

                return [
                    'total_reserved_eur' => 0,
                    'pending_count' => 0,
                    'released_count' => 0,
                    'upcoming_releases' => [],
                    'future_releases' => [],
                    'error' => true
                ];
            }
        });
    }

    /**
     * Get chargeback metrics with caching
     */
    #[Computed]
    public function getChargebackMetricsProperty()
    {
        $cacheKey = "merchant-{$this->merchant->id}-chargebacks-{$this->period}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_DURATION), function () {
            try {
                // Get chargebacks summary for the selected period
                $chargeback = DB::table('chargeback_trackings')
                    ->select([
                        DB::raw('COUNT(*) as total_count'),
                        DB::raw('SUM(amount_eur) as total_amount_eur')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->whereBetween('processing_date', [
                        $this->dateRange['start']->format('Y-m-d H:i:s'),
                        $this->dateRange['end']->format('Y-m-d H:i:s')
                    ])
                    ->first();

                // Get chargebacks by status
                $byStatus = DB::table('chargeback_trackings')
                    ->select([
                        'current_status as status',
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(amount_eur) as amount_eur')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->whereBetween('processing_date', [
                        $this->dateRange['start']->format('Y-m-d H:i:s'),
                        $this->dateRange['end']->format('Y-m-d H:i:s')
                    ])
                    ->groupBy('current_status')
                    ->get()
                    ->toArray();

                // Get monthly trend (last 12 months)
                $monthlyTrend = DB::table('chargeback_trackings')
                    ->select([
                        DB::raw(DB::connection()->getDriverName() === 'pgsql' 
                            ? 'TO_CHAR(processing_date, \'YYYY-MM\') as month_year'
                            : 'DATE_FORMAT(processing_date, "%Y-%m") as month_year'),
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(amount_eur) as amount_eur')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->where('processing_date', '>=', Carbon::now()->subMonths(12))
                    ->groupBy('month_year')
                    ->orderBy('month_year')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'month' => Carbon::createFromFormat('Y-m', $item->month_year)->format('M Y'),
                            'count' => $item->count,
                            'amount_eur' => $item->amount_eur
                        ];
                    })
                    ->toArray();

                return [
                    'total_count' => $chargeback->total_count ?? 0,
                    'total_amount_eur' => $chargeback->total_amount_eur ?? 0,
                    'by_status' => $byStatus,
                    'monthly_trend' => $monthlyTrend
                ];
            } catch (\Exception $e) {
                logger()->error('Error fetching chargeback metrics', [
                    'merchant_id' => $this->merchant->id,
                    'error' => $e->getMessage()
                ]);

                return [
                    'total_count' => 0,
                    'total_amount_eur' => 0,
                    'by_status' => [],
                    'monthly_trend' => [],
                    'error' => true
                ];
            }
        });
    }

    /**
     * Get all-time metrics with caching
     */
    #[Computed]
    public function getLifetimeMetricsProperty()
    {
        $cacheKey = "merchant-{$this->merchant->id}-lifetime-metrics";

        return Cache::remember($cacheKey, now()->addHours(12), function () {
            try {
                // Get all-time data using a single query for each metric type

                // Fees
                $fees = DB::table('fee_histories')
                    ->select([
                        DB::raw('SUM(fee_amount_eur) / 100 as total'),
                        DB::raw('COUNT(*) as count')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->first();

                // Chargebacks
                $chargebacks = DB::table('chargeback_trackings')
                    ->select([
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(amount_eur) as amount_eur')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->first();

                // Reserves
                $reserves = DB::table('rolling_reserve_entries')
                    ->select([
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(CASE WHEN status = "pending" THEN reserve_amount_eur ELSE 0 END) / 100 as pending_amount'),
                        DB::raw('SUM(CASE WHEN status = "released" THEN reserve_amount_eur ELSE 0 END) / 100 as released_amount')
                    ])
                    ->where('merchant_id', $this->merchant->id)
                    ->first();

                // For transactions, try to get data from the repository
                $totalSalesEur = 0;
                $transactionCount = 0;

                if (isset($this->transactionRepository)) {
                    try {
                        // Use a broad date range to get "all time" data
                        $allTimeRange = [
                            'start' => Carbon::now()->subYears(10)->startOfDay()->format('Y-m-d'),
                            'end' => Carbon::now()->endOfDay()->format('Y-m-d')
                        ];

                        $transactions = $this->transactionRepository->getMerchantTransactions(
                            $this->merchant->account_id,
                            $allTimeRange
                        );

                        if ($transactions && $transactions->isNotEmpty()) {
                            $currencies = $transactions->pluck('currency')->unique()->toArray();
                            $exchangeRates = $this->transactionRepository->getExchangeRates($allTimeRange, $currencies);
                            $totals = $this->transactionRepository->calculateTransactionTotals($transactions, $exchangeRates, $this->merchant->account_id);

                            foreach ($totals as $currencyTotals) {
                                $totalSalesEur += $currencyTotals['total_sales_eur'] ?? 0;
                                $transactionCount += $currencyTotals['transaction_sales_count'] ?? 0;
                            }
                        }
                    } catch (\Exception $e) {
                        logger()->error('Error fetching all-time transaction data', [
                            'merchant_id' => $this->merchant->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Calculate transaction average (if we have both sales and count)
                $averageTransaction = 0;
                if ($transactionCount > 0 && $totalSalesEur > 0) {
                    $averageTransaction = $totalSalesEur / $transactionCount;
                }

                return [
                    'total_sales_eur' => $totalSalesEur,
                    'transaction_count' => $transactionCount,
                    'average_transaction' => $averageTransaction,
                    'fee_total' => $fees->total ?? 0,
                    'fee_count' => $fees->count ?? 0,
                    'chargeback_count' => $chargebacks->count ?? 0,
                    'chargeback_amount_eur' => $chargebacks->amount_eur ?? 0,
                    'reserve_count' => $reserves->count ?? 0,
                    'reserve_amount_eur' => $reserves->pending_amount ?? 0,
                    'reserve_released_eur' => $reserves->released_amount ?? 0,
                ];
            } catch (\Exception $e) {
                logger()->error('Error fetching all-time metrics', [
                    'merchant_id' => $this->merchant->id,
                    'error' => $e->getMessage()
                ]);

                return [
                    'total_sales_eur' => 0,
                    'transaction_count' => 0,
                    'average_transaction' => 0,
                    'fee_total' => 0,
                    'fee_count' => 0,
                    'chargeback_count' => 0,
                    'chargeback_amount_eur' => 0,
                    'reserve_count' => 0,
                    'reserve_amount_eur' => 0,
                    'reserve_released_eur' => 0,
                    'error' => true
                ];
            }
        });
    }

    /**
     * Calculate performance metrics
     */
    #[Computed]
    public function getPerformanceMetricsProperty()
    {
        $transactions = $this->getTransactionMetricsProperty();

        // Calculate success rate, chargeback rate, etc.
        $totalAttempts = $transactions['transaction_count'] + $transactions['declined_count'];
        $successRate = $totalAttempts > 0 ? ($transactions['transaction_count'] / $totalAttempts * 100) : 0;

        $chargebackRate = $transactions['transaction_count'] > 0
            ? ($transactions['chargeback_count'] / $transactions['transaction_count'] * 100)
            : 0;

        $refundRate = $transactions['transaction_count'] > 0
            ? ($transactions['refund_count'] / $transactions['transaction_count'] * 100)
            : 0;

        return [
            'success_rate' => $successRate,
            'chargeback_rate' => $chargebackRate,
            'refund_rate' => $refundRate,
        ];
    }

    /**
     * Render the component
     */
    public function render()
    {
        $rollingReserveMetrics = $this->getRollingReserveMetricsProperty();

        // Set isLoading to false once data is fetched
        $this->isLoading = false;

        return view('livewire.merchant-analytics', [
            'merchant' => $this->merchant,
            'transactionMetrics' => $this->getTransactionMetricsProperty(),
            'feeMetrics' => $this->getFeeMetricsProperty(),
            'rollingReserveMetrics' => $rollingReserveMetrics,
            'upcomingReleases' => $rollingReserveMetrics['upcoming_releases'] ?? [],
            'chargebackMetrics' => $this->getChargebackMetricsProperty(),
            'lifetimeMetrics' => $this->getLifetimeMetricsProperty(),
            'performanceMetrics' => $this->getPerformanceMetricsProperty(),
            'period' => $this->period,
            'dateRange' => $this->dateRange,
            'isLoading' => $this->isLoading,
        ]);
    }
}
