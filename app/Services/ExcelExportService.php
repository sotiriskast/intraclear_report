<?php

namespace App\Services;

use App\Services\Excel\ReserveExcelFormatter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment, NumberFormat};
use Carbon\Carbon;

class ExcelExportService
{
    /**
     * @var Spreadsheet Current spreadsheet being generated
     */
    protected $spreadsheet;
    /**
     * @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet Current worksheet being modified
     */
    protected $currentSheet;
    /**
     * @var int Current row position in the active worksheet
     */
    protected $currentRow = 1;

    /**
     * Initialize the Excel export service
     *
     * @param DynamicLogger $logger Service for logging export operations
     * @param ReserveExcelFormatter $reserveFormatter Service for formatting reserve sections
     */
    public function __construct(
        private readonly DynamicLogger $logger,
        private ReserveExcelFormatter  $reserveFormatter
    )
    {
    }

    /**
     * Generate a complete settlement report for a merchant
     *
     * @param int $merchantId ID of the merchant
     * @param array $settlementData Settlement data containing shop and transaction information
     * @param array $dateRange Array with 'start' and 'end' dates for the settlement period
     *
     * @return string Path to the generated Excel file
     * @throws \Exception If report generation fails
     */
    public function generateReport(int $merchantId, array $settlementData, array $dateRange)
    {
        try {
            $this->spreadsheet = new Spreadsheet();
            $this->spreadsheet->removeSheetByIndex(0);

            foreach ($settlementData['data'] as $shopData) {
                foreach ($shopData['transactions_by_currency'] as $currencyData) {
                    $this->createShopCurrencySheet($shopData, $currencyData, $dateRange);
                }
            }

            return $this->saveReport($merchantId, $dateRange);

        } catch (\Exception $e) {
            $this->logger->log('error', 'Error generating Excel report: ' . $e->getMessage(), [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Create a worksheet for a specific shop and currency combination
     *
     * @param array $shopData Shop information including ID and name
     * @param array $currencyData Transaction and fee data for specific currency
     * @param array $dateRange Settlement period date range
     */
    protected function createShopCurrencySheet($shopData, $currencyData, $dateRange)
    {
        $sheetName = $this->createSheetName($shopData, $currencyData['currency']);
        $this->currentSheet = $this->spreadsheet->createSheet();
        $this->currentSheet->setTitle($sheetName);
        $this->currentRow = 1;

        $this->addCompanyHeader();
        $this->addMerchantDetails($shopData, $currencyData, $dateRange);
        $this->addChargeDetails($currencyData);
        $this->addRefundChargeDetails($currencyData);
        $this->addGeneratedReserveDetails($currencyData);
        $this->addRefundedReserveDetails($currencyData);
        $this->addSummarySection($currencyData);
        $this->formatSheet();
    }

    /**
     * Generate a valid worksheet name from shop data and currency
     *
     * @param array $shopData Shop information
     * @param string $currency Currency code
     * @return string Sanitized worksheet name
     */
    protected function createSheetName(array $shopData, string $currency): string
    {
        $shopName = preg_replace('/[^\w\d]/', '_', $shopData['corp_name']);
        return substr($shopName, 0, 15) . '_' . $shopData['shop_id'] . '_' . $currency;
    }

    /**
     * Add company header section to current worksheet
     */
    protected function addCompanyHeader(): void
    {
        $this->currentSheet->setCellValue('A1', 'INTRACLEAR LIMITED');
        $this->currentSheet->setCellValue('F1', 'Issue date: ' . Carbon::now()->format('d/m/Y'));
        $this->currentSheet->getStyle('A1:F1')
            ->getAlignment()->setWrapText(true);
        $this->currentSheet->getRowDimension(1)->setRowHeight(
            40 * (substr_count($this->currentSheet->getCell('A1')->getValue(), "\n") + 1)
        );
        $this->currentRow += 1;
        $this->addSectionHeader('Member Details');
        $this->currentSheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFD700']
            ]
        ]);
    }

    /**
     * Add merchant details section to worksheet
     *
     * @param array $shopData Shop information
     * @param array $currencyData Currency-specific data
     * @param array $dateRange Settlement period
     */
    protected function addMerchantDetails(array $shopData, array $currencyData, array $dateRange): void
    {
        $this->currentRow = 3;
        $details = [
            ['Member ID', $shopData['account_id']],
            ['Company Name', $shopData['corp_name']],
            ['Terminal Id', $shopData['shop_id']],
            ['Processing Currency', $currencyData['currency']],
            ['Settlement Currency', 'EUR'],
            ['Settle Transaction Period',
                Carbon::parse($dateRange['start'])->format('d/m/Y') . '-' .
                Carbon::parse($dateRange['end'])->format('d/m/Y')
            ]
        ];

        foreach ($details as $detail) {
            $this->currentSheet->setCellValue('A' . $this->currentRow, $detail[0]);

            $this->currentSheet->setCellValueExplicit(
                'E' . $this->currentRow,
                $detail[1],
                DataType::TYPE_STRING
            );
            $this->currentSheet->getStyle('E' . $this->currentRow)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $this->currentSheet->mergeCells('A' . $this->currentRow . ':D' . $this->currentRow);
            $this->currentSheet->mergeCells('E' . $this->currentRow . ':G' . $this->currentRow);
            $this->currentRow++;
        }
    }

    /**
     * Add fee and charge details section
     * Includes MDR, transaction fees, declined fees, etc.
     *
     * @param array $currencyData Currency-specific data including fees
     */
    protected function addChargeDetails(array $currencyData): void
    {
        $this->addSectionHeader('Charge Details');

        $headers = ['Charge Name', 'Rate/Fee', 'Terminal', 'Count', 'Amount',
            'Total ' . $currencyData['currency'], 'Total EUR'];
        $this->addTableHeaders($headers);

        if (!isset($currencyData['fees']) || !is_array($currencyData['fees'])) {
            $this->logger->log('warning', 'No fees data found or invalid format', [
                'currency_data' => $currencyData
            ]);
            return;
        }

        // Sort fees by type (standard fees first, then others)
        $standardFeeTypes = [
            'MDR Fee', 'Transaction Fee', 'Declined Fee', 'Monthly Fee', 'Setup Fee',
            'Payout Fee', 'Refund Fee', 'Chargeback Fee',
            'Mastercard High Risk Fee', 'Visa High Risk Fee'
        ];

        $sortedFees = collect($currencyData['fees'])->sortBy(function ($fee) use ($standardFeeTypes) {
            $index = array_search($fee['fee_type'], $standardFeeTypes);
            return $index !== false ? $index : 999;
        });
        $isFirstFee = true;
        foreach ($sortedFees as $fee) {
            $this->currentSheet->setCellValue('A' . $this->currentRow, $fee['fee_type']);
            $this->currentSheet->setCellValue('B' . $this->currentRow, $fee['fee_rate']);
            // Set the appropriate count based on fee type
            $count = match ($fee['fee_type']) {
                'Declined Fee' => $fee['transactionData']['transaction_declined_count'] ?? 0,
                'Refund Fee' => $fee['transactionData']['transaction_refunds_count'] ?? 0,
                'Chargeback Fee' => $fee['transactionData']['total_chargeback_count'] ?? 0,
                'Transaction Fee' => $fee['transactionData']['transaction_sales_count'] ?? 0,
                default => ''
            };
            $this->currentSheet->setCellValue('D' . $this->currentRow, $count);
            if ($isFirstFee) {
                $this->currentSheet->setCellValue('E' . $this->currentRow, $fee['transactionData']['total_sales'] ?? 0);
                $isFirstFee = false;
            } else {
                $this->currentSheet->setCellValue('E' . $this->currentRow, '');
            }
            // Calculate original currency amount
            $originalAmount = $fee['fee_amount'] / $fee['transactionData']['exchange_rate'];
            $this->currentSheet->setCellValue('F' . $this->currentRow, $originalAmount);
            $this->currentSheet->setCellValue('G' . $this->currentRow, $fee['fee_amount']); // EUR amount

            $this->currentRow++;
        }

        // Add total row
        $lastRow = $this->currentRow - 1;
        if ($lastRow > $this->currentRow - 2) {
            $this->currentRow++;
            $this->currentSheet->setCellValue('A' . $this->currentRow, 'Total');
            $this->currentSheet->setCellValue('F' . $this->currentRow,
                '=SUM(F' . ($this->currentRow - $sortedFees->count() - 1) . ':F' . ($lastRow) . ')');
            $this->currentSheet->setCellValue('G' . $this->currentRow,
                '=SUM(G' . ($this->currentRow - $sortedFees->count() - 1) . ':G' . ($lastRow) . ')');

            // Format total row
            $this->currentSheet->getStyle('A' . $this->currentRow . ':G' . $this->currentRow)
                ->getFont()
                ->setBold(true);

            $this->currentSheet->getStyle('F' . $this->currentRow . ':G' . $this->currentRow)
                ->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
        }
    }

    protected function addRefundChargeDetails(array $currencyData): void
    {
        $this->currentRow += 2;
        $this->addSectionHeader('Refund/Chargeback Details');
        $this->currentRow++;
        //HEADER
        $this->currentSheet->setCellValue('A' . $this->currentRow, 'Charge Name');
        $this->currentSheet->mergeCells('A' . $this->currentRow . ':E' . $this->currentRow);
        $this->currentSheet->setCellValue('F' . $this->currentRow, 'Total ' . $currencyData['currency']);
        $this->currentSheet->setCellValue('G' . $this->currentRow, 'Total EUR');
        $this->currentSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true);
        $this->currentSheet->getStyle('F' . $this->currentRow)->getFont()->setBold(true);
        $this->currentSheet->getStyle('G' . $this->currentRow)->getFont()->setBold(true);
        //END HEADER
        $startRow = $this->currentRow + 1;
        $this->currentRow++;

        // Declined Charge back
        $this->currentSheet->setCellValue('A' . $this->currentRow, 'Return Chargeback');
        $this->currentSheet->mergeCells('A' . $this->currentRow . ':E' . $this->currentRow);
        $this->currentSheet->setCellValue('F' . $this->currentRow, $currencyData['total_declined_chargeback_amount']);
        $this->currentSheet->setCellValue('G' . $this->currentRow, $currencyData['total_declined_chargeback_amount_eur']);
        $this->currentRow++;
        // Refund Ammount
        $this->currentSheet->setCellValue('A' . $this->currentRow, 'Refund Amount');
        $this->currentSheet->mergeCells('A' . $this->currentRow . ':E' . $this->currentRow);
        $this->currentSheet->setCellValue('F' . $this->currentRow, $currencyData['total_refunds_amount']);
        $this->currentSheet->setCellValue('G' . $this->currentRow, $currencyData['total_refunds_amount_eur']);
        $this->currentRow++;
        //Chargeback
        $this->currentSheet->setCellValue('A' . $this->currentRow, 'Chargeback');
        $this->currentSheet->mergeCells('A' . $this->currentRow . ':E' . $this->currentRow);
        $this->currentSheet->setCellValue('F' . $this->currentRow, $currencyData['total_approved_chargeback_amount'] + $currencyData['total_processing_chargeback_amount']);
        $this->currentSheet->setCellValue('G' . $this->currentRow, $currencyData['total_approved_chargeback_amount_eur'] + $currencyData['total_processing_chargeback_amount_eur']);
        $this->currentRow += 2;
        //Total Calculation
        $this->currentSheet->setCellValue('A' . $this->currentRow, 'Total Refund Amount');
        $this->currentSheet->mergeCells('A' . $this->currentRow . ':E' . $this->currentRow);
        $this->currentSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true);
        $this->currentSheet->getStyle('F' . $this->currentRow)->getFont()->setBold(true);
        $this->currentSheet->getStyle('G' . $this->currentRow)->getFont()->setBold(true);

        // Add formulas for total calculation
        // Formula: Processing + Approved - Declined
        $this->currentSheet->setCellValue(
            'F' . $this->currentRow,
            "=F{$startRow}+F" . ($startRow + 1) . "-F" . ($startRow + 2)
        );
        $this->currentSheet->setCellValue(
            'G' . $this->currentRow,
            "=G{$startRow}+G" . ($startRow + 1) . "-G" . ($startRow + 2)
        );

    }

    /**
     * Add table Rolling Reserve with consistent formatting
     *
     * @param array $currencyData Currency-specific data including Rolling Reserve
     */
    protected function addGeneratedReserveDetails(array $currencyData): void
    {
        $this->reserveFormatter->formatGeneratedReserves(
            $this->currentSheet,
            $currencyData,
            $this->currentRow
        );
    }

    /**
     * Add table Released Rolling Reserve with consistent formatting
     * *
     * * @param array $currencyData Currency-specific data including Released Rolling Reserve
     */
    protected function addRefundedReserveDetails(array $currencyData): void
    {
        $this->reserveFormatter->formatReleasedReserves(
            $this->currentSheet,
            $currencyData,
            $this->currentRow
        );
    }

    /**
     * Add table for summary details
     * *
     * * @param array $currencyData Array of summary details
     */
    protected function addSummarySection(array $currencyData): void
    {
        $this->currentRow += 2;
        $this->addSectionHeader('Summary');

        // Helper function to safely get total from either array or model
        $getTotal = function ($data, $key) {
            if (empty($data)) {
                return 0;
            }

            // If it's a single model
            if ($data instanceof \Illuminate\Database\Eloquent\Model) {
                return $data->{$key} ?? 0;
            }

            // If it's a collection
            if ($data instanceof \Illuminate\Database\Eloquent\Collection) {
                return $data->sum($key);
            }

            // If it's an array
            if (is_array($data)) {
                return array_sum(array_column($data, $key));
            }

            return 0;
        };

        $summaryItems = [
            [
                'Total Processing Amount',
                $currencyData['total_sales_amount'] ?? 0,
                $currencyData['total_sales_amount_eur'] ?? 0
            ],
            [
                'Total Fees',
                '',
                $getTotal($currencyData['fees'] ?? [], 'amount')
            ],
            [
                'Total Rolling Reserve',
                '',
                $getTotal($currencyData['rolling_reserve'] ?? [], 'reserve_amount_eur')
            ],
            [
                'Released Reserve',
                '',
                $getTotal($currencyData['releaseable_reserve'] ?? [], 'reserve_amount_eur')
            ]
        ];

        foreach ($summaryItems as $item) {
            $this->currentRow++;
            $this->currentSheet->setCellValue('A' . $this->currentRow, $item[0]);
            if ($item[1] !== '') {
                $this->currentSheet->setCellValue('E' . $this->currentRow, $item[1]);
            }
            $this->currentSheet->setCellValue('G' . $this->currentRow, $item[2]);
        }
    }

    /**
     * Add section header with consistent styling
     *
     * @param string $title Header title text
     */
    protected function addSectionHeader(string $title): void
    {
        $this->currentSheet->setCellValue('A' . $this->currentRow, $title);
        $this->currentSheet->mergeCells('A' . $this->currentRow . ':G' . $this->currentRow);
        $this->currentSheet->getStyle('A' . $this->currentRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4472C4']
            ]
        ]);
    }

    /**
     * Add table headers with consistent formatting
     *
     * @param array $headers Array of header texts
     */
    protected function addTableHeaders(array $headers): void
    {
        $this->currentRow++;
        foreach ($headers as $index => $header) {
            $column = chr(65 + $index);
            $this->currentSheet->setCellValue($column . $this->currentRow, $header);
            $this->currentSheet->getStyle($column . $this->currentRow)->getFont()->setBold(true);
        }
        $this->currentRow++;
    }

    /**
     * Apply consistent formatting to the entire worksheet
     * Includes column widths, borders, and number formatting
     */
    protected function formatSheet(): void
    {
        foreach (range('A', 'G') as $column) {
            $this->currentSheet->getColumnDimension($column)->setAutoSize(true);
        }
        $this->currentSheet->mergeCells('A1:E1');
        $this->currentSheet->mergeCells('F1:G1');

        $this->currentSheet->getStyle('E:G')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

        $this->currentSheet->getStyle('A1:G' . $this->currentRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $this->currentSheet->getStyle('A1:G' . $this->currentRow)
            ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Save the generated report to disk
     *
     * @param int $merchantId Merchant ID for filename
     * @param array $dateRange Date range for filename
     * @return string Path to saved file
     * @throws \Exception If saving fails
     */
    protected function saveReport(int $merchantId, array $dateRange): string
    {
        $fileName = sprintf(
            'settlement_report_%s_%s_%s.xlsx',
            $merchantId,
            Carbon::parse($dateRange['start'])->format('YmdHis'),
            Carbon::parse($dateRange['end'])->format('YmdHis')
        );
        $path = storage_path('app/reports/' . $fileName);
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($path);
        return $path;
    }
}

