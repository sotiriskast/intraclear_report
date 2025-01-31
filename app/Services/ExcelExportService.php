<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment, NumberFormat};
use Carbon\Carbon;

class ExcelExportService
{
    protected $spreadsheet;
    protected $currentSheet;
    protected $currentRow = 1;

    public function __construct(private DynamicLogger $logger)
    {
    }

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

    protected function createShopCurrencySheet($shopData, $currencyData, $dateRange)
    {
        $sheetName = $this->createSheetName($shopData, $currencyData['currency']);
        $this->currentSheet = $this->spreadsheet->createSheet();
        $this->currentSheet->setTitle($sheetName);
        $this->currentRow = 1;

        $this->addCompanyHeader();
        $this->addMerchantDetails($shopData, $currencyData, $dateRange);
        $this->addChargeDetails($currencyData);
        $this->addGeneratedReserveDetails($currencyData);
        $this->addRefundedReserveDetails($currencyData);
        $this->addSummarySection($currencyData);
        $this->formatSheet();
    }

    protected function createSheetName($shopData, $currency)
    {
        $shopName = preg_replace('/[^\w\d]/', '_', $shopData['corp_name']);
        return substr($shopName, 0, 15) . '_' . $shopData['shop_id'] . '_' . $currency;
    }

    protected function addCompanyHeader()
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

    protected function addMerchantDetails($shopData, $currencyData, $dateRange)
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

    protected function addChargeDetails($currencyData)
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
                'Chargeback Fee' => $fee['transactionData']['chargeback_count'] ?? 0,
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

    protected function addGeneratedReserveDetails($currencyData)
    {
        $this->currentRow += 2;
        $this->addSectionHeader('Generated Reserve Details');

        // Add safety check
        if (!isset($currencyData['rolling_reserve']) || !is_array($currencyData['rolling_reserve'])) {
            $this->logger->log('warning', 'No rolling reserve data found or invalid format', [
                'currency_data' => $currencyData
            ]);
            return;
        }

        foreach ($currencyData['rolling_reserve'] as $reserve) {
            // Validate reserve data
            if (!is_array($reserve)) {
                $this->logger->log('warning', 'Invalid reserve entry format', [
                    'reserve' => $reserve
                ]);
                continue;
            }

            $this->currentRow++;
            $this->currentSheet->setCellValue('A' . $this->currentRow, 'Rolling Reserve');

            // Safely access array values with defaults
            $percentage = $reserve['percentage'] ?? 10; // Default to 10%
            $originalAmount = $reserve['original_amount'] ?? 0;
            $reserveAmountEur = $reserve['reserve_amount_eur'] ?? 0;

            $this->currentSheet->setCellValue('B' . $this->currentRow, $percentage . '%');
            $this->currentSheet->setCellValue('E' . $this->currentRow, $originalAmount);
            $this->currentSheet->setCellValue('G' . $this->currentRow, $reserveAmountEur);
        }
    }

    protected function addRefundedReserveDetails($currencyData)
    {
        $this->currentRow += 2;
        $this->addSectionHeader('Refunded Reserve Details');

        if (!empty($currencyData['releaseable_reserve'])) {
            foreach ($currencyData['releaseable_reserve'] as $reserve) {
                $this->currentRow++;
                $this->currentSheet->setCellValue('A' . $this->currentRow, 'Released Reserve');
                $this->currentSheet->setCellValue('E' . $this->currentRow, $reserve['original_amount']);
                $this->currentSheet->setCellValue('G' . $this->currentRow, $reserve['reserve_amount_eur']);
            }
        }
    }

    protected function addSummarySection($currencyData)
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

    protected function addSectionHeader($title)
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

    protected function addTableHeaders($headers)
    {
        $this->currentRow++;
        foreach ($headers as $index => $header) {
            $column = chr(65 + $index);
            $this->currentSheet->setCellValue($column . $this->currentRow, $header);
            $this->currentSheet->getStyle($column . $this->currentRow)->getFont()->setBold(true);
        }
        $this->currentRow++;
    }

    protected function formatSheet()
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

    protected function saveReport($merchantId, $dateRange)
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

