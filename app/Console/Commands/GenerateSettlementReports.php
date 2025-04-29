<?php

namespace App\Console\Commands;

use App\Mail\SettlementReportGenerated;
use App\Services\DynamicLogger;
use App\Services\ExcelExportService;
use App\Services\Settlement\SettlementService;
use App\Services\ZipExportService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Console command for generating settlement reports
 *
 * This command handles:
 * - Generating settlement reports for merchants
 * - Processing transactions for specified date ranges
 * - Creating Excel reports per merchant/currency
 * - Bundling reports into ZIP archives
 * - Sending email notifications
 *
 * @property SettlementService $settlementService Service for generating settlement data
 * @property ExcelExportService $excelService Service for creating Excel reports
 * @property ZipExportService $zipService Service for creating ZIP archives
 * @property DynamicLogger $logger Service for logging operations
 */
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
                          {--end-date= : End date (Y-m-d)}';

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
    )
    {
        parent::__construct();

    }

    /**
     * Execute the command
     *
     * @throws \Exception If report generation fails
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

            $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
            $dateRange = ['start' => $startDate, 'end' => $endDate];

            // Get merchants to process with both internal ID and account_id
            $merchants = $this->getMerchants();
            $generatedFiles = [];
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

                if (!empty($settlementData['data'])) {
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
                $recipients = collect(config('settlement.report_recipients', []))
                    ->filter()
                    ->values();
                if ($recipients->isEmpty()) {
                    $this->warn('No recipients configured for settlement report notifications');
                    return;
                }
                $this->info('Sending emails to: ' . $recipients->join(', '));
                $this->logger->log('info', "Sending emails to" . $recipients->join(', '));
                // Send email to each recipient
                $recipients->each(function ($recipient) use ($zipPath, $dateRange, $generatedFiles) {
                    try {
                        Mail::to($recipient)->send(new SettlementReportGenerated(
                            zipPath: $zipPath,
                            dateRange: $dateRange,
                            fileCount: count($generatedFiles)
                        ));

                        $this->info("Email sent successfully to: {$recipient}");
                    } catch (\Throwable $e) {

                        $this->logger->log('error', "Settlement report email failed", [
                            'recipient' => $recipient,
                            'error' => $e->getMessage(),
                            'zipPath' => $zipPath
                        ]);
                    }
                });
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

    /**
     * Store reference to generated ZIP archive
     *
     * @param string $zipPath Path to the ZIP file
     * @param array $dateRange Date range for the settlement period
     * @param array $reports Array of report file paths included in the ZIP
     */
    protected function storeZipReference(string $zipPath, array $dateRange, array $reports): void
    {
        DB::connection('pgsql')
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

    /**
     * Get list of merchants to process
     *
     * @return array Array of merchant objects with id, account_id, and name
     */
    protected function getMerchants(): array
    {
        $query = DB::connection('pgsql')
            ->table('merchants')
            ->select(['id', 'account_id', 'name'])
            ->where('active', 1);

        if ($merchantId = $this->option('merchant-id')) {
            $query->where('account_id', $merchantId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get merchant's shop data for the given date range
     *
     * @param int $merchantId Merchant's account ID
     * @param array $dateRange Date range for the settlement period
     * @return array Array of shop data including shop_id and corp_name
     */
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

    /**
     * Get currencies used in transactions for a specific shop
     *
     * @param int $merchantId Merchant's account ID
     * @param int $shopId Shop ID
     * @param array $dateRange Date range for the settlement period
     * @return array Array of currency codes
     */
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

    /**
     * Store reference to generated report in database
     *
     * @param int $merchantId Internal merchant ID
     * @param string $filePath Path to the generated report
     * @param array $dateRange Date range for the settlement period
     */
    protected function storeReportReference(int $merchantId, string $filePath, array $dateRange): void
    {
        DB::connection('pgsql')
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
