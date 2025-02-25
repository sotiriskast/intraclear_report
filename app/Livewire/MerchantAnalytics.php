<?php

namespace App\Livewire;

use App\Repositories\FeeRepository;
use App\Repositories\MerchantRepository;
use App\Repositories\RollingReserveRepository;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MerchantAnalytics extends Component
{
    public $merchants = [];
    public $selectedMerchantId = null;
    public $rollingReserveSummary = [];
    public $rollingReserveTotalEur = 0;
    public $feeHistorySummary = [];
    public $totalReserves = [];
    public $totalFees = [];
    public $feeTypeDistribution = [];
    public $reserveTrends = [];

    public function mount(
        MerchantRepository $merchantRepository,
        RollingReserveRepository $rollingReserveRepository,
        FeeRepository $feeRepository
    ) {
        // Get active merchants
        $this->merchants = $merchantRepository->getActive();

        // If merchants exist, select the first one by default
        if ($this->merchants->isNotEmpty()) {
            $this->selectedMerchantId = $this->merchants->first()->id;
            $this->loadMerchantData($this->selectedMerchantId);
        }

        // Calculate total reserves and fees across all merchants
        $this->calculateTotals($merchantRepository, $rollingReserveRepository, $feeRepository);
        $this->feeTypeDistribution = [];
        foreach ($this->feeHistorySummary as $fee) {
            $feeType = $fee['fee_type']['name'];
            if (!isset($this->feeTypeDistribution[$feeType])) {
                $this->feeTypeDistribution[$feeType] = 0;
            }
            $this->feeTypeDistribution[$feeType] += $fee['fee_amount_eur'] / 100;
        }

        // Calculate reserve trends (last 6 months)
        $this->reserveTrends = [];
        foreach ($this->rollingReserveSummary['pending_reserves'] ?? [] as $currency => $amount) {
            $this->reserveTrends[$currency] = [
                'amount' => $amount,
                'percentage' => ($amount / $this->totalReserves['total_eur']) * 100
            ];
        }
    }

    public function loadMerchantData($merchantId)
    {
        $this->selectedMerchantId = $merchantId;

        // Inject repositories via method to keep code clean
        $rollingReserveRepository = app(RollingReserveRepository::class);
        $feeRepository = app(FeeRepository::class);

        // Get rolling reserve summary for selected merchant
        $this->rollingReserveSummary = $rollingReserveRepository->getReserveSummary($merchantId);

        // Calculate total EUR for the merchant's rolling reserves
        $this->rollingReserveTotalEur = array_sum($this->rollingReserveSummary['pending_reserves_eur'] ?? []);

        // Get fee history for selected merchant
        $this->feeHistorySummary = $feeRepository->getFeeApplicationsInDateRange(
            $merchantId,
            null,
            now()->subMonths(6)->format('Y-m-d'),
            now()->format('Y-m-d')
        );
    }

    private function calculateTotals(
        MerchantRepository $merchantRepository,
        RollingReserveRepository $rollingReserveRepository,
        FeeRepository $feeRepository
    ) {
        $this->totalReserves = [
            'pending' => 0,
            'upcoming_releases' => 0,
            'currencies' => [],
            'total_eur' => 0
        ];

        $this->totalFees = [
            'total_amount_eur' => 0,
            'currencies' => [],
            'fee_types' => []
        ];

        foreach ($this->merchants as $merchant) {
            $reserveSummary = $rollingReserveRepository->getReserveSummary($merchant->id);

            // Aggregate reserves
            foreach ($reserveSummary['pending_reserves'] ?? [] as $currency => $amount) {
                // Ensure currency exists in total
                if (!isset($this->totalReserves['currencies'][$currency])) {
                    $this->totalReserves['currencies'][$currency] = 0;
                }
                $this->totalReserves['currencies'][$currency] += $amount;
            }

            // Aggregate upcoming releases
            foreach ($reserveSummary['upcoming_releases'] ?? [] as $currency => $amount) {
                $this->totalReserves['upcoming_releases'] += $amount;
            }

            // Aggregate total pending reserve count
            $this->totalReserves['pending'] += $reserveSummary['pending_count'] ?? 0;

            // Get fee history for the last 6 months
            $feeHistory = $feeRepository->getFeeApplicationsInDateRange(
                $merchant->id,
                null,
                now()->subMonths(6)->format('Y-m-d'),
                now()->format('Y-m-d')
            );

            // Aggregate fee history
            foreach ($feeHistory as $fee) {
                $feeAmountEur = $fee['fee_amount_eur'] / 100; // Convert from cents
                $this->totalFees['total_amount_eur'] += $feeAmountEur;

                // Aggregate fees by currency
                $baseCurrency = $fee['base_currency'] ?? 'Unknown';
                if (!isset($this->totalFees['currencies'][$baseCurrency])) {
                    $this->totalFees['currencies'][$baseCurrency] = 0;
                }
                $this->totalFees['currencies'][$baseCurrency] += $feeAmountEur;
            }
        }
        // Total reserves is already in EUR from repository
        $this->totalReserves['total_eur'] = array_sum($this->totalReserves['currencies']);
    }
    #[Computed]
    public function feeHistoryJson()
    {
        return json_encode($this->feeHistorySummary);
    }

    /**
     * Get rolling reserve data as JSON for charts
     */
    #[Computed]
    public function rollingReserveJson()
    {
        return json_encode($this->rollingReserveSummary);
    }
    /**
     * Get fee history data as JSON for charts
     * Using standard method instead of Computed to ensure it's always fresh
     */
    public function getFeeHistoryJson()
    {
        // Add debug info
        \Log::info('Fee History for charts:', ['data' => $this->feeHistorySummary]);

        return json_encode($this->feeHistorySummary);
    }

    /**
     * Get rolling reserve data as JSON for charts
     * Using standard method instead of Computed to ensure it's always fresh
     */
    public function getRollingReserveJson()
    {
        // Add debug info
        \Log::info('Rolling Reserve for charts:', ['data' => $this->rollingReserveSummary]);

        return json_encode($this->rollingReserveSummary);
    }

    public function render()
    {
        // Ensure data is available for the view
        $feeHistoryJson = $this->getFeeHistoryJson();
        $rollingReserveJson = $this->getRollingReserveJson();

        return view('livewire.merchant-analytics', [
            'feeHistoryJson' => $feeHistoryJson,
            'rollingReserveJson' => $rollingReserveJson
        ]);
    }
}
