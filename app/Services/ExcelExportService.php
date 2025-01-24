<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment, NumberFormat};
use Carbon\Carbon;

class ExcelExportService
{
    protected $spreadsheet;
    protected $currentSheet;
    protected $currentRow = 1;

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
            \Log::error('Error generating Excel report: ' . $e->getMessage(), [
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
        $this->currentSheet->setCellValue('F1', 'Issue date: ' . Carbon::now()->format('d.m.Y'));

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
                Carbon::parse($dateRange['start'])->format('d.m.Y') . '-' .
                Carbon::parse($dateRange['end'])->format('d.m.Y')
            ]
        ];

        foreach ($details as $detail) {
            $this->currentSheet->setCellValue('A' . $this->currentRow, $detail[0]);
            $this->currentSheet->setCellValue('E' . $this->currentRow, $detail[1]);
            $this->currentRow++;
        }
    }

    protected function addChargeDetails($currencyData)
    {
        $this->currentRow += 2;
        $this->addSectionHeader('Charge Details');

        $headers = ['Charge Name', 'Rate/Fee', 'Terminal', 'Count', 'Amount',
            'Total ' . $currencyData['currency'], 'Total EUR'];
        $this->addTableHeaders($headers);

        foreach ($currencyData['fees'] as $fee) {
            $this->currentRow++;
            $this->currentSheet->setCellValue('A' . $this->currentRow, $fee['type']);
            $this->currentSheet->setCellValue('B' . $this->currentRow, $fee['is_percentage'] ? $fee['rate'] . '%' : $fee['rate']);
            $this->currentSheet->setCellValue('C' . $this->currentRow, ''); // Terminal
            $this->currentSheet->setCellValue('D' . $this->currentRow, $fee['count']);
            $this->currentSheet->setCellValue('E' . $this->currentRow, $fee['base_amount']);
            $this->currentSheet->setCellValue('F' . $this->currentRow, $fee['amount']);
            $this->currentSheet->setCellValue('G' . $this->currentRow, $fee['amount']); // EUR amount
        }
    }

    protected function addGeneratedReserveDetails($currencyData)
    {
        $this->currentRow += 2;
        $this->addSectionHeader('Generated Reserve Details');

        foreach ($currencyData['rolling_reserve'] as $reserve) {
            $this->currentRow++;
            $this->currentSheet->setCellValue('A' . $this->currentRow, 'Rolling Reserve');
            $this->currentSheet->setCellValue('B' . $this->currentRow, $reserve['percentage'] . '%');
            $this->currentSheet->setCellValue('E' . $this->currentRow, $reserve['original_amount']);
            $this->currentSheet->setCellValue('G' . $this->currentRow, $reserve['reserve_amount_eur']);
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

        $summaryItems = [
            ['Total Processing Amount', $currencyData['total_sales_amount'], $currencyData['total_sales_amount_eur']],
            ['Total Fees', '', array_sum(array_column($currencyData['fees'], 'amount'))],
            ['Total Rolling Reserve', '', array_sum(array_column($currencyData['rolling_reserve'], 'reserve_amount_eur'))],
            ['Released Reserve', '', array_sum(array_column($currencyData['releaseable_reserve'], 'reserve_amount_eur'))]
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
    }

    protected function formatSheet()
    {
        foreach (range('A', 'G') as $column) {
            $this->currentSheet->getColumnDimension($column)->setAutoSize(true);
        }

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
            Carbon::parse($dateRange['start'])->format('Ymd'),
            Carbon::parse($dateRange['end'])->format('Ymd')
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

