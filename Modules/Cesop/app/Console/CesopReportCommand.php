<?php

namespace Modules\Cesop\Console;

use Illuminate\Console\Command;
use Modules\Cesop\Services\CesopReportService;
use Carbon\Carbon;

class CesopReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cesop:report
                            {--quarter=}
                            {--year=}
                            {--merchants=*}
                            {--shops=*}
                            {--output=}
                            {--format=console}
                            {--threshold=25}
                            {--psp-data=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CESOP report for European BINs with more than 25 transactions';

    /**
     * @var CesopReportService
     */
    protected $reportService;

    /**
     * Create a new command instance.
     *
     * @param CesopReportService $reportService
     * @return void
     */
    public function __construct(CesopReportService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get command options with defaults
        $quarter = $this->option('quarter') ?: Carbon::now()->subQuarter()->quarter;
        $year = $this->option('year') ?: Carbon::now()->subQuarter()->year;
        $merchantIds = $this->option('merchants');
        $shopIds = $this->option('shops');
        $format = $this->option('format') ?: 'console';
        $outputPath = $this->option('output');
        $threshold = $this->option('threshold') ?: 25;

        // Load PSP data
        $pspData = null;
        if ($this->option('psp-data')) {
            $pspData = $this->loadPspData($this->option('psp-data'));
        }

        // Calculate date range for display
        $startMonth = (($quarter - 1) * 3) + 1;
        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
        $endDate = (clone $startDate)->addMonths(3)->subDay()->endOfDay();

        $this->info("Generating CESOP report for Q{$quarter} {$year}");
        $this->info("Date range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("Transaction threshold: {$threshold}");

        // Generate report using service
        $result = $this->reportService->generateReport(
            $quarter,
            $year,
            $merchantIds,
            $shopIds,
            $threshold,
            $pspData
        );

        if (!$result['success']) {
            $this->error($result['message']);
            return 1;
        }

        $stats = $result['data']['stats'];
        $xml = $result['data']['xml'];

        $this->info("Found " . $stats['transaction_groups'] . " qualifying transaction groups");
        $this->info("Processed " . $stats['processed_merchants'] . " merchants and " . $stats['processed_shops'] . " shops");
        $this->newLine();

        // Save the XML file if requested
        if ($format === 'xml' || $this->confirm('Would you like to generate an XML CESOP report?', false)) {
            // Determine output path
            if (!$outputPath) {
                $outputPath = storage_path("app/cesop_report_q{$quarter}_{$year}_" . date('Ymd_His') . ".xml");
            }

            // Create directory if it doesn't exist
            $directory = dirname($outputPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save XML to file
            file_put_contents($outputPath, $xml);

            $this->info("XML CESOP report generated and saved to: {$outputPath}");
        }

        $this->info("CESOP report generation completed successfully for {$stats['processed_shops']} shops.");
        return 0;
    }

    /**
     * Load PSP data from JSON file or use defaults
     *
     * @param string|null $path Path to JSON file
     * @return array
     */
    protected function loadPspData(?string $path): array
    {
        $defaults = [
            'bic' => config('cesop.psp.bic', 'ABCDEF12XXX'),
            'name' => config('cesop.psp.name', 'Intraclear Provider'),
            'country' => config('cesop.psp.country', 'CY'),
            'tax_id' => config('cesop.psp.tax_id', 'CY12345678X')
        ];

        if ($path && file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            return array_merge($defaults, $data);
        }

        return $defaults;
    }
}
