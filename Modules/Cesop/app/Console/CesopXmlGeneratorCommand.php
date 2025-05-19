<?php

namespace Modules\Cesop\Console;

use Illuminate\Console\Command;
use Modules\Cesop\Services\CesopXmlGeneratorService;
use Modules\Cesop\Services\CesopXmlValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Helper\ProgressBar;

class CesopXmlGeneratorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cesop:generate-xml-report
                            {--quarter= : Reporting quarter (1-4)}
                            {--year= : Reporting year}
                            {--merchants=* : Specific merchant IDs to include}
                            {--shops=* : Specific shop IDs to include}
                            {--output= : Output directory for files}
                            {--threshold=25 : Minimum number of transactions to report}
                            {--psp-data= : Path to JSON file with PSP data}
                            {--validate : Validate the generated XML against CESOP schema}
                            {--schema-path= : Path to CESOP XSD schema file}
                            {--no-progress : Disable progress bar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CESOP XML report for European BINs with more than 25 transactions';

    /**
     * @var CesopXmlGeneratorService
     */
    protected $reportService;

    /**
     * @var CesopXmlValidator
     */
    protected $xmlValidator;

    /**
     * Create a new command instance.
     *
     * @param CesopXmlGeneratorService $reportService
     * @param CesopXmlValidator $xmlValidator
     * @return void
     */
    public function __construct(CesopXmlGeneratorService $reportService, CesopXmlValidator $xmlValidator)
    {
        parent::__construct();
        $this->reportService = $reportService;
        $this->xmlValidator = $xmlValidator;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('CESOP XML Report Generator');
        $this->info('==========================');
        $this->newLine();

        // Get command options with defaults
        $quarter = $this->option('quarter') ?: Carbon::now()->subQuarter()->quarter;
        $year = $this->option('year') ?: Carbon::now()->subQuarter()->year;
        $merchantIds = $this->option('merchants');
        $shopIds = $this->option('shops');
        $outputPath = $this->option('output');
        $threshold = $this->option('threshold') ?: 25;
        $shouldValidate = $this->option('validate') ?: false;
        $schemaPath = $this->option('schema-path');
        $showProgress = !$this->option('no-progress');

        // Determine output directory
        if (empty($outputPath)) {
            $outputPath = Storage::path('cesop/xml/' . date('Y-m-d_His'));
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

        $this->info("Generating CESOP XML report for Q{$quarter} {$year}");
        $this->info("Date range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("Transaction threshold: {$threshold}");
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

        // Create report service with progress bar
        $reportService = new CesopXmlGeneratorService($quarter, $year, $threshold, $pspData, $progressBar);

        // Generate XML report using the updated service
        $result = $reportService->generateReport($merchantIds, $shopIds, $outputPath);

        // Make sure we finish the progress bar
        if ($progressBar) {
            $progressBar->finish();
            $this->newLine(2);
        }

        if (!$result['success']) {
            $this->error($result['message']);
            return 1;
        }

        $stats = $result['data']['stats'];
        $xmlFilePath = $result['data']['file'];
        $fileSize = $result['data']['file_size'];

        $this->info("Generated XML file: " . basename($xmlFilePath));
        $this->info("Generated XML file full path: " . $xmlFilePath);
        $this->info("File size: " . number_format($fileSize) . " bytes");
        $this->info("Included {$stats['merchant_count']} merchants with {$stats['transaction_count']} transactions");
        $this->info("Total transaction amount: " . number_format($stats['total_amount'], 2));
        $this->newLine();

        // Validate the XML if requested
        if ($shouldValidate) {
            // If schema path provided, set it in the validator
            if ($schemaPath) {
                $this->xmlValidator->setSchemaPath($schemaPath);
            }
            $this->validateXml($xmlFilePath);
        }

        $this->info("CESOP XML report generation completed successfully.");

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
            $this->line("Loading PSP data from: {$path}");
            $data = json_decode(file_get_contents($path), true);
            return array_merge($defaults, $data);
        }

        $this->line("Using default PSP data");
        return $defaults;
    }

    /**
     * Validate XML against CESOP XSD schema using the improved validator
     *
     * @param string $xmlPath Path to the XML file
     * @return void
     */
    protected function validateXml(string $xmlPath): void
    {
        $this->line('Validating XML against CESOP schema...');

        // Use the improved validator
        $result = $this->xmlValidator->validateXmlFile($xmlPath);

        if (!$result['valid']) {
            $this->error("XML validation failed with " . count($result['errors']) . " errors:");

            foreach ($result['errors'] as $error) {
                $this->error("  - {$error}");
            }

            if (!empty($result['warnings'])) {
                $this->warn("Validation warnings:");
                foreach ($result['warnings'] as $warning) {
                    $this->warn("  - {$warning}");
                }
            }

            // If schema validation failed, run business rule validation
            $this->line('Performing business rule validation...');
            $content = file_get_contents($xmlPath);
            $businessRules = $this->xmlValidator->validateBusinessRules($content);

            if (!$businessRules['valid']) {
                $this->error("Business rule validation failed with " . count($businessRules['errors']) . " errors:");
                foreach ($businessRules['errors'] as $error) {
                    $this->error("  - {$error}");
                }
            } else {
                $this->info("Business rule validation passed!");
                $this->line("The file may still have schema issues but meets basic CESOP requirements.");
            }

            $this->warn("Note: Schema validation issues may prevent the file from being accepted by tax authorities.");
            $this->warn("Please make sure you have the latest XSD schema files from the EU tax authority portal.");
        } else {
            $this->info("XML validation successful! The file is compliant with the CESOP schema.");

            if (!empty($result['warnings'])) {
                $this->warn("Validation warnings:");
                foreach ($result['warnings'] as $warning) {
                    $this->warn("  - {$warning}");
                }
            }
        }
    }
}
