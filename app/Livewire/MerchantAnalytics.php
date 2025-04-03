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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Lazy]
#[Layout('layouts.app', ['header' => 'Merchant Analytics'])]
#[Title('Merchant Analytics')]
class MerchantAnalytics extends Component
{
    public $merchant;
    public $period = 'last30days';
    public $dateRange = [];
    public $currency = 'all';
    public $isLoading = false; // Add loading state

    protected $feeRepository;
    protected $reserveRepository;
    protected $chargebackRepository;
    protected $transactionRepository;

    // Add currencies from all sources as a property
    protected $allCurrencies = [];

    protected function getListeners()
    {
        return [
            'periodUpdated' => 'updatedPeriod',
        ];
    }

    public function boot(
        FeeRepository                  $feeRepository,
        RollingReserveRepository       $reserveRepository,
        ChargebackTrackingRepository   $chargebackRepository,
        TransactionRepositoryInterface $transactionRepository
    )
    {
        $this->feeRepository = $feeRepository;
        $this->reserveRepository = $reserveRepository;
        $this->chargebackRepository = $chargebackRepository;
        $this->transactionRepository = $transactionRepository;
    }

    public function mount(Merchant $merchant)
    {
        $this->merchant = $merchant;
        $this->setDateRange('last30days');

        // Initialize currencies early
        $this->loadAllCurrencies();
    }

    // Load all currencies from all data sources upfront
    protected function loadAllCurrencies()
    {
        // Get unique currencies from rolling reserves
        $reserveCurrencies = RollingReserveEntry::where('merchant_id', $this->merchant->id)
            ->distinct()
            ->pluck('original_currency')
            ->toArray();

        // Get unique currencies from fee history
        $feeCurrencies = FeeHistory::where('merchant_id', $this->merchant->id)
            ->distinct()
            ->pluck('base_currency')
            ->toArray();

        // Get unique currencies from chargebacks
        $chargebackCurrencies = ChargebackTracking::where('merchant_id', $this->merchant->id)
            ->distinct()
            ->pluck('currency')
            ->toArray();

        // Combine and deduplicate
        $this->allCurrencies = array_unique(array_merge($reserveCurrencies, $feeCurrencies, $chargebackCurrencies));
        sort($this->allCurrencies);

        // If no currencies found, add EUR as default
        if (empty($this->allCurrencies)) {
            $this->allCurrencies = ['EUR'];
        }
    }

    public function updatedPeriod($value)
    {
        $this->isLoading = true;
        $this->setDateRange($value);
        $this->isLoading = false;
    }

    public function updatedCurrency($value)
    {
        $this->isLoading = false;
    }

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
            case 'alltime':
                $this->dateRange = [
                    'start' => Carbon::now()->subYears(15)->startOfDay(),
                    'end' => Carbon::now()->endOfDay()
                ];
                break;
            default:
                $this->dateRange = [
                    'start' => Carbon::now()->subDays(30)->startOfDay(),
                    'end' => Carbon::now()->endOfDay()
                ];
        }
    }
    #[Computed]
    #[Renderless]
    public function getTransactionMetricsProperty()
    {
        try {
            // First, try to get data from TransactionRepository if available
            if (isset($this->transactionRepository)) {
                try {
                    // Get transactions within date range
                    $transactions = $this->transactionRepository->getMerchantTransactions(
                        $this->merchant->account_id,
                        $this->dateRange,
                        $this->currency !== 'all' ? $this->currency : null
                    );

                    if ($transactions && $transactions->isNotEmpty()) {
                        // Get exchange rates
                        $currencies = $transactions->pluck('currency')->unique()->toArray();
                        $exchangeRates = $this->transactionRepository->getExchangeRates($this->dateRange, $currencies);

                        // Calculate transaction totals
                        $totals = $this->transactionRepository->calculateTransactionTotals($transactions, $exchangeRates);

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
                            $summary['total_sales'] += $currencyTotals['total_sales'];
                            $summary['total_sales_eur'] += $currencyTotals['total_sales_eur'];
                            $summary['transaction_count'] += $currencyTotals['transaction_sales_count'];
                            $summary['declined_count'] += $currencyTotals['transaction_declined_count'];
                            $summary['refund_count'] += $currencyTotals['transaction_refunds_count'];
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

            // As a last resort, compute metrics from fee history
            $feeHistory = $this->getFeeMetricsProperty();
            $transactionFees = collect($feeHistory['fees_by_type'])->filter(function ($fee) {
                return stripos($fee['name'], 'transaction') !== false;
            })->first();

            if ($transactionFees && isset($transactionFees['count']) && $transactionFees['count'] > 0) {
                return [
                    'total_sales' => 0, // Unknown actual amount
                    'total_sales_eur' => 0, // Unknown actual amount
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
                'error' => $e->getMessage()
            ];
        }
    }

    public function getFeeMetricsProperty()
    {
        try {
            // Get fees for the selected period
            $feeHistory = FeeHistory::query()
                ->where('merchant_id', $this->merchant->id)
                ->whereBetween('applied_date', [$this->dateRange['start'], $this->dateRange['end']])
                ->when($this->currency !== 'all', function ($query) {
                    return $query->where('base_currency', $this->currency);
                })
                ->get();

            // Get all-time fees - ALWAYS get all fees regardless of date range
            $allTimeFeeHistory = FeeHistory::query()
                ->where('merchant_id', $this->merchant->id)
                ->when($this->currency !== 'all', function ($query) {
                    return $query->where('base_currency', $this->currency);
                })
                ->get();

            // Calculate period fee metrics
            $totalFees = $feeHistory->sum('fee_amount_eur') / 100;
            $feesByType = $feeHistory->groupBy('fee_type_id')
                ->map(function ($fees, $feeTypeId) {
                    $feeName = $fees->first()->feeType->name ?? "Fee Type $feeTypeId";
                    return [
                        'name' => $feeName,
                        'amount' => $fees->sum('fee_amount_eur') / 100,
                        'count' => $fees->count()
                    ];
                })
                ->sortByDesc('amount')
                ->values()
                ->toArray();

            // Calculate all-time fee metrics
            $allTimeTotalFees = $allTimeFeeHistory->sum('fee_amount_eur') / 100;
            $allTimeFeesByType = $allTimeFeeHistory->groupBy('fee_type_id')
                ->map(function ($fees, $feeTypeId) {
                    $feeName = $fees->first()->feeType->name ?? "Fee Type $feeTypeId";
                    return [
                        'name' => $feeName,
                        'amount' => $fees->sum('fee_amount_eur') / 100,
                        'count' => $fees->count()
                    ];
                })
                ->sortByDesc('amount')
                ->values()
                ->toArray();

            // Get monthly fee trend
            $monthlyTrend = $allTimeFeeHistory
                ->groupBy(function ($fee) {
                    return Carbon::parse($fee->applied_date)->format('Y-m');
                })
                ->map(function ($fees, $yearMonth) {
                    $month = Carbon::createFromFormat('Y-m', $yearMonth)->format('M Y');
                    return [
                        'month' => $month,
                        'amount' => $fees->sum('fee_amount_eur') / 100,
                        'count' => $fees->count()
                    ];
                })
                ->sortBy(function ($item, $key) {
                    return $key; // Sort by year-month
                })
                ->values()
                ->toArray();

            return [
                'total_fees' => $totalFees,
                'fees_by_type' => $feesByType,
                'fee_count' => $feeHistory->count(),
                'all_time_total_fees' => $allTimeTotalFees,
                'all_time_fees_by_type' => $allTimeFeesByType,
                'all_time_fee_count' => $allTimeFeeHistory->count(),
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
                'all_time_total_fees' => 0,
                'all_time_fees_by_type' => [],
                'all_time_fee_count' => 0,
                'monthly_trend' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    public function getRollingReserveMetricsProperty()
    {
        try {
            // Always get ALL reserves regardless of date range
            $summary = $this->reserveRepository->getReserveSummary(
                $this->merchant->id,
                $this->currency !== 'all' ? $this->currency : null
            );

            // Make sure we have a total_reserved_eur value
            if (!isset($summary['total_reserved_eur']) && isset($summary['pending_reserves_eur'])) {
                $summary['total_reserved_eur'] = array_sum($summary['pending_reserves_eur']);
            }

            // Get breakdown by release months - don't filter by date range
            $futureReleases = RollingReserveEntry::query()
                ->where('merchant_id', $this->merchant->id)
                ->where('status', 'pending')
                ->when($this->currency !== 'all', function ($query) {
                    return $query->where('original_currency', $this->currency);
                })
                ->get()
                ->groupBy(function ($entry) {
                    return Carbon::parse($entry->release_due_date)->format('Y-m');
                })
                ->map(function ($entries, $yearMonth) {
                    $month = Carbon::createFromFormat('Y-m', $yearMonth)->format('M Y');
                    return [
                        'month' => $month,
                        'amount_eur' => $entries->sum('reserve_amount_eur') / 100,
                        'count' => $entries->count()
                    ];
                })
                ->sortBy(function ($item, $key) {
                    return $key; // Sort by year-month
                })
                ->values()
                ->toArray();

            return array_merge($summary, [
                'future_releases' => $futureReleases
            ]);
        } catch (\Exception $e) {
            logger()->error('Error fetching rolling reserve metrics', [
                'merchant_id' => $this->merchant->id,
                'error' => $e->getMessage()
            ]);

            return [
                'pending_reserves' => [],
                'pending_reserves_eur' => [],
                'pending_count' => 0,
                'released_count' => 0,
                'upcoming_releases' => [],
                'future_releases' => [],
                'total_reserved_eur' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getChargebackMetricsProperty()
    {
        try {
            // Get chargebacks for the selected period
            $chargebacks = ChargebackTracking::query()
                ->where('merchant_id', $this->merchant->id)
                ->whereBetween('processing_date', [$this->dateRange['start'], $this->dateRange['end']])
                ->when($this->currency !== 'all', function ($query) {
                    return $query->where('currency', $this->currency);
                })
                ->get();

            // Get all-time chargebacks for monthly trend
            $allTimeChargebacks = ChargebackTracking::query()
                ->where('merchant_id', $this->merchant->id)
                ->when($this->currency !== 'all', function ($query) {
                    return $query->where('currency', $this->currency);
                })
                ->get();

            $totalAmount = $chargebacks->sum('amount_eur');
            $byStatus = $chargebacks->groupBy('current_status')
                ->map(function ($items, $status) {
                    return [
                        'status' => $status,
                        'count' => $items->count(),
                        'amount_eur' => $items->sum('amount_eur')
                    ];
                })
                ->values()
                ->toArray();

            // Use all-time data for monthly trend to ensure we have a complete picture
            $monthlyTrend = $allTimeChargebacks->groupBy(function ($chargeback) {
                return Carbon::parse($chargeback->processing_date)->format('Y-m');
            })
                ->map(function ($items, $yearMonth) {
                    $month = Carbon::createFromFormat('Y-m', $yearMonth)->format('M Y');
                    return [
                        'month' => $month,
                        'count' => $items->count(),
                        'amount_eur' => $items->sum('amount_eur')
                    ];
                })
                ->sortBy(function ($item, $key) {
                    return $key; // Sort by year-month
                })
                ->values()
                ->toArray();

            return [
                'total_count' => $chargebacks->count(),
                'total_amount_eur' => $totalAmount,
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
                'error' => $e->getMessage()
            ];
        }
    }

    public function getCurrenciesProperty()
    {
        // Return cached currencies instead of querying them each time
        return $this->allCurrencies;
    }


    /**
     * Get upcoming rolling reserve releases (exact dates)
     * This method fetches the next 8 specific release dates from the database
     */
    public function getUpcomingReleasesProperty()
    {
        // Use Laravel's remember method to cache the query results
        return cache()->remember(
            "merchant-{$this->merchant->id}-releases-{$this->currency}",
            now()->addMinutes(30),  // Cache for 30 minutes
            function () {
                try {
                    // Get next 8 specific upcoming release dates with amounts
                    $upcomingReleases = RollingReserveEntry::query()
                        ->where('merchant_id', $this->merchant->id)
                        ->where('status', 'pending')
                        ->where('release_due_date', '>=', Carbon::now())
                        ->when($this->currency !== 'all', function ($query) {
                            return $query->where('original_currency', $this->currency);
                        })
                        // Group by exact release date (not just month)
                        ->select([
                            'release_due_date',
                            DB::raw('COUNT(*) as count'),
                            DB::raw('SUM(reserve_amount_eur) / 100 as amount_eur')
                        ])
                        ->groupBy('release_due_date')
                        ->orderBy('release_due_date', 'asc')
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

                    return $upcomingReleases;
                } catch (\Exception $e) {
                    logger()->error('Error fetching upcoming rolling reserve releases', [
                        'merchant_id' => $this->merchant->id,
                        'error' => $e->getMessage()
                    ]);

                    return [];
                }
            }
        );
    }

    /**
     * Get all-time transaction metrics by combining data from all sources
     */
    public function getAllTimeMetricsProperty()
    {
        try {
            // Get all fee history - without date filtering
            $allFees = FeeHistory::query()
                ->where('merchant_id', $this->merchant->id)
                ->when($this->currency !== 'all', function ($query) {
                    return $query->where('base_currency', $this->currency);
                })
                ->get();

            // Get all chargebacks - without date filtering
            $allChargebacks = ChargebackTracking::query()
                ->where('merchant_id', $this->merchant->id)
                ->when($this->currency !== 'all', function ($query) {
                    return $query->where('currency', $this->currency);
                })
                ->get();

            // Get all rolling reserves - without date filtering
            $allReserves = RollingReserveEntry::query()
                ->where('merchant_id', $this->merchant->id)
                ->when($this->currency !== 'all', function ($query) {
                    return $query->where('original_currency', $this->currency);
                })
                ->get();

            // For transactions, try to get data directly from the repository if available
            $totalSalesEur = 0;
            $transactionCount = 0;

            if (isset($this->transactionRepository)) {
                try {
                    // Use a very broad date range to get "all time" data
                    $allTimeRange = [
                        'start' => Carbon::now()->subYears(15)->startOfDay(),
                        'end' => Carbon::now()->endOfDay()
                    ];

                    $transactions = $this->transactionRepository->getMerchantTransactions(
                        $this->merchant->account_id,
                        $allTimeRange,
                        $this->currency !== 'all' ? $this->currency : null
                    );

                    if ($transactions && $transactions->isNotEmpty()) {
                        $currencies = $transactions->pluck('currency')->unique()->toArray();
                        $exchangeRates = $this->transactionRepository->getExchangeRates($allTimeRange, $currencies);
                        $totals = $this->transactionRepository->calculateTransactionTotals($transactions, $exchangeRates);

                        foreach ($totals as $currencyTotals) {
                            $totalSalesEur += $currencyTotals['total_sales_eur'];
                            $transactionCount += $currencyTotals['transaction_sales_count'];
                        }
                    }
                } catch (\Exception $e) {
                    logger()->error('Error fetching all-time transaction data', [
                        'merchant_id' => $this->merchant->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // If we couldn't get transaction count from repository, try to estimate from fees
            if ($transactionCount == 0) {
                $transactionFees = collect($allFees)->filter(function ($fee) {
                    return stripos($fee->feeType->name ?? '', 'transaction') !== false;
                });

                if ($transactionFees->isNotEmpty()) {
                    $transactionCount = $transactionFees->count();
                }
            }

            // Compile metrics
            return [
                'total_sales_eur' => $totalSalesEur,
                'transaction_count' => $transactionCount,
                'fee_total' => $allFees->sum('fee_amount_eur') / 100,
                'fee_count' => $allFees->count(),
                'chargeback_count' => $allChargebacks->count(),
                'chargeback_amount_eur' => $allChargebacks->sum('amount_eur'),
                'reserve_count' => $allReserves->count(),
                'reserve_amount_eur' => $allReserves->where('status', 'pending')->sum('reserve_amount_eur') / 100,
                'reserve_released_eur' => $allReserves->where('status', 'released')->sum('reserve_amount_eur') / 100
            ];
        } catch (\Exception $e) {
            logger()->error('Error fetching all-time metrics', [
                'merchant_id' => $this->merchant->id,
                'error' => $e->getMessage()
            ]);

            return [
                'total_sales_eur' => 0,
                'transaction_count' => 0,
                'fee_total' => 0,
                'fee_count' => 0,
                'chargeback_count' => 0,
                'chargeback_amount_eur' => 0,
                'reserve_count' => 0,
                'reserve_amount_eur' => 0,
                'reserve_released_eur' => 0
            ];
        }
    }

    public function render()
    {
        return view('livewire.merchant-analytics', [
            'merchant' => $this->merchant,
            'transactionMetrics' => $this->getTransactionMetricsProperty(),
            'feeMetrics' => $this->getFeeMetricsProperty(),
            'rollingReserveMetrics' => $this->getRollingReserveMetricsProperty(),
            'chargebackMetrics' => $this->getChargebackMetricsProperty(),
            'currencies' => $this->getCurrenciesProperty(),
            'lifetimeMetrics' => $this->getAllTimeMetricsProperty(),
            'period' => $this->period,
            'dateRange' => $this->dateRange,
            'isLoading' => $this->isLoading,
            'upcomingReleases' => $this->getUpcomingReleasesProperty(),

        ]);
    }
}
