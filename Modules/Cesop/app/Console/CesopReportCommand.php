<?php

namespace Modules\Cesop\Console;

use Illuminate\Console\Command;
use Modules\Cesop\Services\CesopReportService;
use Modules\Cesop\Services\CesopXmlValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

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
                            {--psp-data=}
                            {--validate}
                            {--schema-path=}';

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
     * @var CesopXmlValidator
     */
    protected $xmlValidator;

    /**
     * Create a new command instance.
     *
     * @param CesopReportService $reportService
     * @param CesopXmlValidator $xmlValidator
     * @return void
     */
    public function __construct(CesopReportService $reportService, CesopXmlValidator $xmlValidator)
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
        $this->info('CESOP Report Generator');
        $this->info('======================');
        $this->newLine();

        // Get command options with defaults
        $quarter = $this->option('quarter') ?: Carbon::now()->subQuarter()->quarter;
        $year = $this->option('year') ?: Carbon::now()->subQuarter()->year;
        $merchantIds = $this->option('merchants');
        $shopIds = $this->option('shops');
        $format = $this->option('format') ?: 'console';
        $outputPath = $this->option('output');
        $threshold = $this->option('threshold') ?: 25;
        $shouldValidate = $this->option('validate') ?: false;
        $schemaPath = $this->option('schema-path');

        // If schema path provided, set it in the validator
        if ($schemaPath) {
            $this->xmlValidator->setSchemaPath($schemaPath);
        }

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
        $this->newLine();

        // Generate report using service
        $this->line('Retrieving transaction data...');
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
            $this->line('Generating XML document...');

            // Determine output path
            if (!$outputPath) {
                $pspCountry = $pspData['country'] ?? config('cesop.psp.country', 'CY');
                $pspBic = $pspData['bic'] ?? config('cesop.psp.bic', 'ABCDEF12XXX');

                // Create a CESOP-compliant filename
                $fileName = sprintf(
                    'PMT-Q%d-%d-%s-%s-1-1.xml',
                    $quarter,
                    $year,
                    $pspCountry,
                    $pspBic
                );

                $outputDir = config('cesop.output_path', storage_path('app/cesop'));
                $outputPath = $outputDir . '/' . $fileName;
            }

            // Create directory if it doesn't exist
            $directory = dirname($outputPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save XML to file
            file_put_contents($outputPath, $xml);

            $this->info("XML CESOP report generated and saved to: {$outputPath}");

            // Validate the XML if requested
            if ($shouldValidate) {
                $this->validateXml($outputPath);
            }
        }

        $this->info("CESOP report generation completed successfully for {$stats['processed_shops']} shops.");

        // Show transaction stats
        $this->line("Total transactions: {$stats['total_transactions']}");
        $formattedAmount = number_format($stats['total_amount'] / 100, 2);
        $this->line("Total amount: {$formattedAmount}");
        $this->newLine();

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
