<?php

namespace App\Console\Commands;

use App\Models\UserNotificationRecipient;
use App\Notifications\SettlementReportGenerated;
use App\Services\DynamicLogger;
use App\Services\ExcelExportService;
use App\Services\Settlement\SettlementService;
use App\Services\ZipExportService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class GenerateSettlementReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intraclear:settlement-generate
                          {--merchant-id= : Specific merchant ID}
                          {--start-date= : Start date (Y-m-d)}
                          {--end-date= : End date (Y-m-d)}
                          {--currency= : Specific currency}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate settlement reports for merchants';

    public function __construct(
        private readonly SettlementService  $settlementService,
        private readonly ExcelExportService $excelService,
        private readonly ZipExportService   $zipService,
        private readonly DynamicLogger      $logger,
    ) {
        parent::__construct();

    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $startDate = $this->option('start-date')
                ?? Carbon::now()
                    ->subWeek()
                    ->startOfWeek(CarbonInterface::MONDAY)
                    ->format('Y-m-d');

            $endDate = $this->option('end-date')
                ?? Carbon::now()
                    ->subWeek()
                    ->endOfWeek(CarbonInterface::SUNDAY)
                    ->format('Y-m-d');

            $dateRange = ['start' => $startDate, 'end' => $endDate];

            // Get merchants to process with both internal ID and account_id
            $merchants = $this->getMerchants();
            $generatedFiles=[];
            foreach ($merchants as $merchant) {
                $this->info("Processing merchant ID: {$merchant->account_id}");

                // Get merchant's shops and their transactions
                $shopData = $this->getMerchantShopData($merchant->account_id, $dateRange);

                if (empty($shopData)) {
                    $this->warn("No data found for merchant ID: {$merchant->account_id}");

                    continue;
                }

                $settlementData = ['data' => []];

                foreach ($shopData as $shop) {
                    $currencies = $this->getShopCurrencies($merchant->account_id, $shop['shop_id'], $dateRange);
                    foreach ($currencies as $currency) {
                        $settlementInfo = $this->settlementService->generateSettlement(
                            $merchant->account_id,
                            $dateRange,
                            $currency
                        );
                        $settlementData['data'][] = [
                            'account_id' => $merchant->account_id,
                            'corp_name' => $shop['corp_name'],
                            'shop_id' => $shop['shop_id'],
                            'transactions_by_currency' => [
                                array_merge(['currency' => $currency], $settlementInfo),
                            ],
                        ];
                    }
                }

                if (! empty($settlementData['data'])) {
                    $filePath = $this->excelService->generateReport(
                        $merchant->account_id,
                        $settlementData,
                        $dateRange
                    );

                    $this->info("Report generated: {$filePath}");
                    $generatedFiles[] = $filePath;
                    // Store reference using internal merchant ID
                    $this->storeReportReference($merchant->id, $filePath, $dateRange);
                }
            }
            // Always create ZIP if files were generated
            if (!empty($generatedFiles)) {
                $zipPath = $this->zipService->createZip($generatedFiles, $dateRange);
                $this->info("ZIP file created: {$zipPath}");
                // Store ZIP reference in database
                $this->storeZipReference($zipPath, $dateRange, $generatedFiles);
                // Send notifications to user's configured recipients
                try {
                    foreach ($merchants as $merchant) {
                        $recipients = UserNotificationRecipient::getActiveRecipients($merchant->id);

                        if (!empty($recipients)) {
                            Notification::route('mail', $recipients)
                                ->notify(new SettlementReportGenerated($zipPath, $dateRange));

                            $this->info("Notifications sent for merchant: {$merchant->account_id}");
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn("Notification failed: {$e->getMessage()}");
                    $this->logger->log('warning', 'Settlement report notification failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->info('All reports generated successfully');

            return 0;

        } catch (\Exception $e) {
            $this->error("Error generating reports: {$e->getMessage()}");
            $this->logger->log('error', 'Settlement report generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function storeZipReference(string $zipPath, array $dateRange, array $reports): void
    {
        DB::connection('mariadb')
            ->table('settlement_report_archives')
            ->insert([
                'zip_path' => $zipPath,
                'start_date' => $dateRange['start'],
                'end_date' => $dateRange['end'],
                'report_count' => count($reports),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    protected function getMerchants(): array
    {
        $query = DB::connection('mariadb')
            ->table('merchants')
            ->select(['id', 'account_id', 'name'])
            ->where('active', 1);

        if ($merchantId = $this->option('merchant-id')) {
            $query->where('account_id', $merchantId);
        }

        return $query->get()->toArray();
    }

    protected function getMerchantShopData(int $merchantId, array $dateRange): array
    {
        return DB::connection('payment_gateway_mysql')
            ->table('shop')
            ->select([
                'shop.id as shop_id',
                'shop.owner_name as corp_name',
            ])
            ->where('shop.account_id', $merchantId)
            ->whereExists(function ($query) use ($dateRange) {
                $query->select(DB::raw(1))
                    ->from('transactions')
                    ->whereColumn('transactions.shop_id', 'shop.id')
                    ->whereBetween('transactions.added', [$dateRange['start'], $dateRange['end']]);
            })
            ->distinct()
            ->get()
            ->map(function ($shop) {
                return [
                    'shop_id' => $shop->shop_id,
                    'corp_name' => $shop->corp_name,
                ];
            })
            ->toArray();
    }

    protected function getShopCurrencies(int $merchantId, int $shopId, array $dateRange): array
    {
        return DB::connection('payment_gateway_mysql')
            ->table('transactions')
            ->where('account_id', $merchantId)
            ->where('shop_id', $shopId)
            ->whereBetween('added', [$dateRange['start'], $dateRange['end']])
            ->distinct()
            ->pluck('currency')
            ->toArray();
    }

    protected function storeReportReference(int $merchantId, string $filePath, array $dateRange): void
    {
        DB::connection('mariadb')
            ->table('settlement_reports')
            ->insert([
                'merchant_id' => $merchantId, // Using internal merchant ID
                'report_path' => $filePath,
                'start_date' => $dateRange['start'],
                'end_date' => $dateRange['end'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
