<?php

namespace Modules\Cesop\Console;

use Illuminate\Console\Command;
use Modules\Cesop\Services\CesopExcelGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Symfony\Component\Console\Helper\ProgressBar;

class CesopExcelGenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cesop:generate-excel-report
                            {--quarter= : Reporting quarter (1-4)}
                            {--year= : Reporting year}
                            {--merchants=* : Specific merchant IDs to include}
                            {--shops=* : Specific shop IDs to include}
                            {--output= : Output directory for files}
                            {--threshold=25 : Minimum number of transactions to report}
                            {--psp-data= : Path to JSON file with PSP data}
                            {--no-progress : Disable progress bar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CESOP report files from transaction data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('CESOP Report Generator');
        $this->info('======================');
        $this->newLine();

        // Get command options with defaults
        $quarter = $this->option('quarter') ?: Carbon::now()->subQuarter()->quarter;
        $year = $this->option('year') ?: Carbon::now()->subQuarter()->year;
        $merchantIds = $this->option('merchants');
        $shopIds = $this->option('shops');
        $outputPath = $this->option('output');
        $threshold = $this->option('threshold') ?: 25;
        $showProgress = !$this->option('no-progress');


        // Determine output directory
        if (empty($outputPath)) {
            $outputPath = Storage::path('cesop/exports/' . date('Y-m-d_His'));
        }

        if (!is_dir($outputPath) && !mkdir($outputPath, 0755, true)) {
            $this->error("Failed to create output directory: {$outputPath}");
            return 1;
        }

        // Load PSP data if provided
        $pspData = null;
        if ($this->option('psp-data')) {
            $pspData = $this->loadPspData($this->option('psp-data'));
        }

        // Calculate date range for display
        $startMonth = (($quarter - 1) * 3) + 1;
        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
        $endDate = (clone $startDate)->addMonths(3)->subDay()->endOfDay();

        $this->info("Generating CESOP Excel files for Q{$quarter} {$year}");
        $this->info("Date range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("Transaction threshold: {$threshold}");
        $this->newLine();

        $this->generateExcelReport($quarter, $year, $threshold, $pspData, $merchantIds, $shopIds, $outputPath, $showProgress);



        return 0;
    }

    /**
     * Generate Excel report
     */
    protected function generateExcelReport(
        int $quarter,
        int $year,
        int $threshold,
        ?array $pspData,
        array $merchantIds,
        array $shopIds,
        string $outputPath,
        bool $showProgress = true
    ): void {
        $this->line('Generating Excel report...');
        $this->newLine();

        // Create progress bar if enabled
        $progressBar = null;
        if ($showProgress) {
            // Initialize with a small number of steps initially (will be updated later)
            $progressBar = $this->output->createProgressBar(5);
            $progressBar->setFormat(
                ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% | %message%'
            );
            $progressBar->setMessage('Starting...');
            $progressBar->start();
        }

        // Create Excel generator service with progress bar
        $generator = new CesopExcelGeneratorService($quarter, $year, $threshold, $pspData, $progressBar);

        // Generate Excel file
        $result = $generator->generateExcelFile($merchantIds, $shopIds, $outputPath);

        // Make sure we finish the progress bar
        if ($progressBar) {
            $progressBar->finish();
            $this->newLine(2);
        }

        if (!$result['success']) {
            $this->error($result['message']);
            return;
        }

        $stats = $result['data']['stats'];
        $filePath = $result['data']['file'];

        $this->info("Generated Excel file: " . basename($filePath));
        $this->info("Generated Excel file full Path: " . $filePath);
        $this->info("Included {$stats['merchant_count']} merchants with {$stats['transaction_count']} transactions");
        $this->info("Total transaction amount: " . number_format($stats['total_amount'], 2));
    }

    /**
     * Generate CSV report
     */
    protected function generateCsvReport(
        int $quarter,
        int $year,
        int $threshold,
        ?array $pspData,
        array $merchantIds,
        array $shopIds,
        string $outputPath,
        bool $showProgress = true
    ): void {
        $this->line('Generating CSV report...');
        $this->newLine();

        // Create progress bar if enabled
        $progressBar = null;
        if ($showProgress) {
            // Initialize with a small number of steps initially (will be updated later)
            $progressBar = $this->output->createProgressBar(5);
            $progressBar->setFormat(
                ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% | %message%'
            );
            $progressBar->setMessage('Starting...');
            $progressBar->start();
        }

        // Create CSV generator service with progress bar
        $generator = new CesopExcelGeneratorService($quarter, $year, $threshold, $pspData, $progressBar);

        // Generate CSV files
        $result = $generator->generateExcelFile($merchantIds, $shopIds, $outputPath);

        // Make sure we finish the progress bar
        if ($progressBar) {
            $progressBar->finish();
            $this->newLine(2);
        }

        if (!$result['success']) {
            $this->error($result['message']);
            return;
        }

        $stats = $result['data']['stats'];
        $files = $result['data']['files'] ?? [];

        $this->info("Generated files for {$stats['merchant_count']} merchants with {$stats['transaction_count']} transactions");
        $this->info("Total transaction amount: " . number_format($stats['total_amount'], 2));

        // Display generated files
        $this->newLine();
        $this->info('Generated files:');
        foreach ($files as $type => $path) {
            $this->line(" - {$type}: " . basename($path));
        }

        // Create ZIP archive if there are multiple files
        if (count($files) > 1) {
            $zipPath = $this->createZipArchive($files, $outputPath, $quarter, $year);
            if ($zipPath) {
                $this->newLine();
                $this->info("Created ZIP archive: " . basename($zipPath));
            }
        }
    }

    /**
     * Load PSP data from JSON file
     *
     * @param string $path Path to JSON file
     * @return array PSP data
     */
    protected function loadPspData(string $path): array
    {
        $defaults = [
            'bic' => config('cesop.psp.bic', 'ABCDEF12XXX'),
            'name' => config('cesop.psp.name', 'Intraclear Provider'),
            'country' => config('cesop.psp.country', 'CY'),
            'tax_id' => config('cesop.psp.tax_id', 'CY12345678X')
        ];

        if (file_exists($path)) {
            $this->line("Loading PSP data from: {$path}");
            $data = json_decode(file_get_contents($path), true);
            return array_merge($defaults, $data);
        }

        $this->line("PSP data file not found, using default values");
        return $defaults;
    }

    /**
     * Create a ZIP archive containing all generated CSV files
     *
     * @param array $files Array of file paths
     * @param string $outputPath Output directory
     * @param int $quarter Reporting quarter
     * @param int $year Reporting year
     * @return string|false Path to ZIP file or false on failure
     */
    protected function createZipArchive(array $files, string $outputPath, int $quarter, int $year)
    {
        $zipFilename = "CESOP_Q{$quarter}_{$year}_" . date('Ymd_His') . ".zip";
        $zipPath = $outputPath . '/' . $zipFilename;

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->error("Could not create ZIP archive");
            return false;
        }

        foreach ($files as $type => $filePath) {
            $zip->addFile($filePath, basename($filePath));
        }

        $zip->close();

        return $zipPath;
    }
}
