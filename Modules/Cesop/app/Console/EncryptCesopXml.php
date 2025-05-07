<?php

namespace Modules\Cesop\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Cesop\Services\PgpEncryptionService;

class EncryptCesopXml extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cesop:encrypt-xml
                           {file : The XML file to encrypt}
                           {--quarter=1 : Reporting quarter (1-4)}
                           {--year=2024 : Reporting year}
                           {--country=CY : Country code (ISO-3166 Alpha 2)}
                           {--pspid=DEFAULT : PSP identifier}
                           {--file-number=1 : File number in sequence}
                           {--total-files=1 : Total number of files}
                           {--output-dir=cesop/encrypted : Output directory}';

    /**
     * The console command description.
     */
    protected $description = 'Encrypt a CESOP XML file using PGP encryption';

    /**
     * @var PgpEncryptionService
     */
    protected $pgpService;

    /**
     * Create a new command instance.
     */
    public function __construct(PgpEncryptionService $pgpService)
    {
        parent::__construct();
        $this->pgpService = $pgpService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $quarter = (int)$this->option('quarter');
        $year = (int)$this->option('year');
        $countryCode = strtoupper($this->option('country'));
        $pspId = $this->option('pspid');
        $fileNumber = $this->option('file-number');
        $totalFiles = $this->option('total-files');
        $outputDir = $this->option('output-dir');

        $this->info("Processing file: {$filePath}");

        // Validate file exists
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        try {
            // Validate inputs
            if ($quarter < 1 || $quarter > 4) {
                $this->error("Quarter must be between 1 and 4");
                return 1;
            }

            if (strlen($countryCode) !== 2) {
                $this->error("Country code must be ISO-3166 Alpha 2 format (2 characters)");
                return 1;
            }

            // Copy file to storage if needed
            $tempPath = $filePath;
            if (!Storage::exists($filePath)) {
                $this->info("Copying file to temporary storage...");
                $fileContent = file_get_contents($filePath);
                $tempPath = 'cesop/temp/' . basename($filePath);
                Storage::put($tempPath, $fileContent);
            }

            // Generate filename
            $outputFilename = $this->pgpService->generateCesopFilename(
                $quarter,
                $year,
                $countryCode,
                $pspId,
                $fileNumber,
                $totalFiles
            );

            $this->info("Output filename: {$outputFilename}");

            // Define output path
            $outputPath = $outputDir . '/' . $outputFilename;

            // Encrypt the XML file
            $encryptedPath = $this->pgpService->encryptXmlFile($tempPath, $outputPath);

            // Clean up the temporary file if we created it
            if ($tempPath !== $filePath) {
                Storage::delete($tempPath);
            }

            $absolutePath = Storage::path($outputPath);
            $this->info("File encrypted successfully!");
            $this->info("Encrypted file saved to: {$absolutePath}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Encryption failed: " . $e->getMessage());
            return 1;
        }
    }
}
