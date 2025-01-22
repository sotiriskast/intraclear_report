<?php

namespace App\Console\Commands;

use App\Services\ExcelExportService;
use App\Services\SettlementService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DB;
use Illuminate\Console\Command;

class GenerateSettlementReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settlement:generate
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

    protected $settlementService;
    protected $excelService;

    public function __construct(
        SettlementService $settlementService,
        ExcelExportService $excelService
    ) {
        parent::__construct();
        $this->settlementService = $settlementService;
        $this->excelService = $excelService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // Get date range
            $startDate = $this->option('start-date')
                ?? Carbon::now()
                    ->subWeek() // Go to last week
                    ->startOfWeek(CarbonInterface::MONDAY) // Get the Monday of last week
                    ->format('Y-m-d');

            $endDate = $this->option('end-date')
                ?? Carbon::now()
                    ->subWeek() // Go to last week
                    ->endOfWeek(CarbonInterface::SUNDAY) // Get the Sunday of last week
                    ->format('Y-m-d');
            // Get merchants to process
            $merchantIds = [];
            if ($this->option('merchant-id')) {
                $merchantIds[] = $this->option('merchant-id');
            } else {
                $merchantIds = DB::connection('payment_gateway_mysql')
                    ->table('account')
                    ->where('active', 1)
                    ->pluck('id')
                    ->toArray();
            }

            $currency = $this->option('currency');
            $dateRange = ['start' => $startDate, 'end' => $endDate];

            foreach ($merchantIds as $merchantId) {
                $this->info("Processing merchant ID: {$merchantId}");

                // Generate settlement data
                $settlementData = $this->settlementService->generateSettlement(
                    $merchantId,
                    $dateRange,
                    $currency
                );

                // Generate Excel report
                $filePath = $this->excelService->generateReport(
                    $merchantId,
                    $settlementData,
                    $dateRange
                );

                $this->info("Report generated: {$filePath}");

                // Store reference in database
                DB::table('generated_reports')->insert([
                    'merchant_id' => $merchantId,
                    'report_path' => $filePath,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'created_at' => now()
                ]);
            }

            $this->info('All reports generated successfully');

        } catch (\Exception $e) {
            $this->error("Error generating reports: {$e->getMessage()}");
            \Log::error("Settlement report generation failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
