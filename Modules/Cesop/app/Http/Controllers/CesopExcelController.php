<?php

namespace Modules\Cesop\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use Modules\Cesop\Services\CesopExcelGeneratorService;
use Carbon\Carbon;
use ZipArchive;

class CesopExcelController extends Controller
{
    /**
     * Display the export interface
     */
    public function index()
    {
        // Get available quarters for dropdown
        $availableQuarters = $this->getAvailableQuarters();

        // Get available merchants for selection
        $availableMerchants = $this->getAvailableMerchants();

        return view('cesop::csv.index', compact('availableQuarters', 'availableMerchants'));
    }

    /**
     * Generate and download data files
     */
    public function generateCsv(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'quarter' => 'required|integer|between:1,4',
            'year' => 'required|integer|between:2020,2030',
            'merchants' => 'nullable|array',
            'shops' => 'nullable|array',
            'threshold' => 'nullable|integer|min:1',
            'format' => 'required|in:csv,excel',
            'psp_data' => 'nullable|array',
        ]);

        // Set default threshold if not provided
        $threshold = $validated['threshold'] ?? 25;

        // Convert empty arrays to empty values for proper filtering
        $merchantIds = !empty($validated['merchants']) ? $validated['merchants'] : [];
        $shopIds = !empty($validated['shops']) ? $validated['shops'] : [];
        $pspData = !empty($validated['psp_data']) ? $validated['psp_data'] : null;

        // Determine output format
        $format = $validated['format'];

        // Generate unique output directory
        $outputDir = Storage::path('cesop/exports/' . date('Ymd_His'));
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if ($format === 'excel') {
            // Use the Excel generator for direct Excel output
            $generator = new CesopExcelGeneratorService(
                $validated['quarter'],
                $validated['year'],
                $threshold,
                $pspData
            );

            $result = $generator->generateExcelFile($merchantIds, $shopIds, $outputDir);

            if (!$result['success']) {
                return back()->with('error', $result['message']);
            }

            $excelPath = $result['data']['file'];
            $excelFileName = basename($excelPath);

            return response()->download($excelPath, $excelFileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        } else {
            // Use the CSV generator for CSV format
            $generator = new CesopExcelGeneratorService(
                $validated['quarter'],
                $validated['year'],
                $threshold,
                $pspData
            );

            // Generate CSV files
            $result = $generator->generateExcelFile($merchantIds, $shopIds, $outputDir);

            if (!$result['success']) {
                return back()->with('error', $result['message']);
            }

            $files = $result['data']['files'];
            $stats = $result['data']['stats'];

            // Create ZIP file with all CSV files
            $zipFileName = "CESOP_CSV_Q{$validated['quarter']}_{$validated['year']}_" . date('Ymd_His') . ".zip";
            $zipPath = Storage::path('cesop/temp/' . $zipFileName);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                return back()->with('error', 'Failed to create ZIP archive');
            }

            foreach ($files as $type => $path) {
                $zip->addFile($path, basename($path));
            }

            $zip->close();

            return response()->download($zipPath, $zipFileName, [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend();
        }
    }

    /**
     * Upload an Excel file and convert it to specified format
     */
    public function uploadExcel(Request $request)
    {
        // Validate request
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
            'output_format' => 'required|in:csv,excel',
        ]);

        $file = $request->file('excel_file');
        $filePath = $file->storeAs('cesop/uploads', $file->getClientOriginalName());
        $fullPath = Storage::path($filePath);
        $outputFormat = $request->input('output_format');

        // Create output directory
        $outputDir = Storage::path('cesop/exports/' . date('Ymd_His'));
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            // Load the Excel file
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);

            if ($outputFormat === 'excel') {
                // Check if the Excel file already has the correct sheets
                $hasAccountsSheet = false;
                $hasPaymentsSheet = false;

                foreach ($spreadsheet->getSheetNames() as $sheetName) {
                    if (strtolower($sheetName) === 'cesop_accounts') {
                        $hasAccountsSheet = true;
                        $spreadsheet->getSheetByName($sheetName)->setTitle('CESOP_Accounts');
                    } elseif (strtolower($sheetName) === 'cesop_payments') {
                        $hasPaymentsSheet = true;
                        $spreadsheet->getSheetByName($sheetName)->setTitle('CESOP_Payments');
                    }
                }

                // If the Excel file doesn't have the correct structure, we need to reorganize it
                if (!$hasAccountsSheet || !$hasPaymentsSheet) {
                    // Create a new spreadsheet with the correct structure
                    $newSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

                    // Create accounts sheet
                    $accountsSheet = $newSpreadsheet->getActiveSheet();
                    $accountsSheet->setTitle('CESOP_Accounts');

                    // Add payments sheet
                    $paymentsSheet = $newSpreadsheet->createSheet();
                    $paymentsSheet->setTitle('CESOP_Payments');

                    // Copy data from original spreadsheet to the new one
                    $sourceSheets = $spreadsheet->getAllSheets();

                    if (count($sourceSheets) >= 2) {
                        // Assuming first sheet is accounts and second is payments
                        $this->copySheetData($sourceSheets[0], $accountsSheet);
                        $this->copySheetData($sourceSheets[1], $paymentsSheet);
                    } elseif (count($sourceSheets) == 1) {
                        // Only one sheet, copy to accounts
                        $this->copySheetData($sourceSheets[0], $accountsSheet);
                    }

                    $spreadsheet = $newSpreadsheet;
                }

                // Save the modified Excel file
                $excelFileName = 'CESOP_Excel_' . date('Ymd_His') . '.xlsx';
                $excelPath = $outputDir . '/' . $excelFileName;

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save($excelPath);

                return response()->download($excelPath, $excelFileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])->deleteFileAfterSend();

            } else {
                // Convert Excel to CSV files
                $csvFiles = [];

                // Process each sheet
                foreach ($spreadsheet->getSheetNames() as $sheetName) {
                    $sheet = $spreadsheet->getSheetByName($sheetName);
                    $csvFileName = $this->sanitizeFilename($sheetName) . '.csv';
                    $csvFilePath = $outputDir . '/' . $csvFileName;

                    // Create CSV writer
                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
                    $writer->setSheetIndex($spreadsheet->getIndex($sheet));
                    $writer->setDelimiter(',');
                    $writer->setEnclosure('"');
                    $writer->save($csvFilePath);

                    $csvFiles[$sheetName] = $csvFilePath;
                }

                // Create ZIP file with all CSV files
                $zipFileName = 'CESOP_CSV_' . date('Ymd_His') . '.zip';
                $zipPath = Storage::path('cesop/temp/' . $zipFileName);

                $zip = new ZipArchive();
                if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                    return back()->with('error', 'Failed to create ZIP archive');
                }

                foreach ($csvFiles as $sheetName => $csvFilePath) {
                    $zip->addFile($csvFilePath, basename($csvFilePath));
                }

                $zip->close();

                return response()->download($zipPath, $zipFileName, [
                    'Content-Type' => 'application/zip',
                ])->deleteFileAfterSend();
            }

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to process Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Copy data from one worksheet to another
     */
    protected function copySheetData($sourceSheet, $targetSheet)
    {
        $maxRow = $sourceSheet->getHighestRow();
        $maxCol = $sourceSheet->getHighestColumn();

        // Copy cell data
        for ($row = 1; $row <= $maxRow; $row++) {
            for ($col = 'A'; $col <= $maxCol; $col++) {
                $cell = $sourceSheet->getCell($col . $row);
                $value = $cell->getValue();
                $targetSheet->setCellValue($col . $row, $value);
            }
        }

        // Auto-size columns
        foreach (range('A', $maxCol) as $col) {
            $targetSheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Get shops for a specific merchant (for AJAX requests)
     */
    public function getShops(Request $request)
    {
        $merchantId = $request->input('merchant_id');

        if (!$merchantId) {
            return response()->json(['success' => false, 'message' => 'Merchant ID is required']);
        }

        $shops = \Illuminate\Support\Facades\DB::connection('payment_gateway_mysql')
            ->table('shop')
            ->select('id', 'owner_name as name')
            ->where('account_id', $merchantId)
            ->where('active', 1)
            ->orderBy('owner_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shops
        ]);
    }

    /**
     * Get available quarters for report generation
     */
    protected function getAvailableQuarters(int $yearsBack = 3): array
    {
        $quarters = [];
        $currentYear = Carbon::now()->year;
        $currentQuarter = ceil(Carbon::now()->month / 3);

        for ($year = $currentYear; $year > $currentYear - $yearsBack; $year--) {
            $maxQuarters = ($year == $currentYear) ? $currentQuarter - 1 : 4;

            for ($quarter = $maxQuarters; $quarter >= 1; $quarter--) {
                $quarters[] = [
                    'year' => $year,
                    'quarter' => $quarter,
                    'label' => "Q{$quarter} {$year}"
                ];
            }
        }

        return $quarters;
    }

    /**
     * Get available merchants
     */
    protected function getAvailableMerchants(): \Illuminate\Support\Collection
    {
        return \Illuminate\Support\Facades\DB::connection('payment_gateway_mysql')
            ->table('account as a')
            ->select('a.id', 'a.corp_name as name')
            ->where('a.active', 1)
            ->orderBy('a.corp_name')
            ->get();
    }

    /**
     * Sanitize a filename to be safe for saving
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove any characters that aren't alphanumeric, underscore, dash or dot
        $filename = preg_replace('/[^\w\-\.]/', '_', $filename);
        return $filename;
    }
}
