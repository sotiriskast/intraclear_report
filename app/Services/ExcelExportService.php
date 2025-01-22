<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{
    Fill,
    Border,
    Alignment,
    NumberFormat
};
use Carbon\Carbon;

class ExcelExportService
{
    protected $spreadsheet;
    protected $sheet;
    protected $currentRow = 1;

    public function generateReport(int $merchantId, array $settlementData, array $dateRange)
    {
        $this->initializeSpreadsheet();

        // Get merchant info
        $merchant = $this->getMerchantInfo($merchantId);

        // Add report header
        $this->addReportHeader($merchant, $dateRange);

        // Add transaction summary
        $this->addTransactionSummary($settlementData['transactions']);

        // Add fees section
        $this->addFeesSection($settlementData['fees']);

        // Add rolling reserve section
        $this->addRollingReserveSection(
            $settlementData['rolling_reserve'],
            $settlementData['releaseable_reserve']
        );

        // Add totals and final calculations
        $this->addTotalCalculations($settlementData);

        return $this->saveReport($merchantId, $dateRange);
    }

    protected function initializeSpreadsheet()
    {
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
        $this->currentRow = 1;
    }

    protected function getMerchantInfo($merchantId)
    {
        return DB::connection('payment_gateway_mysql')
            ->table('account')
            ->where('id', $merchantId)
            ->first();
    }

    protected function addReportHeader($merchant, $dateRange)
    {
        $this->sheet->setCellValue('A1', 'SETTLEMENT REPORT');
        $this->sheet->mergeCells('A1:H1');

        $this->sheet->setCellValue('A2', 'Merchant:');
        $this->sheet->setCellValue('B2', $merchant->corp_name);

        $this->sheet->setCellValue('A3', 'Period:');
        $this->sheet->setCellValue('B3', Carbon::parse($dateRange['start'])->format('d/m/Y') .
            ' - ' . Carbon::parse($dateRange['end'])->format('d/m/Y'));

        // Style header
        $this->sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        $this->currentRow = 5;
    }

    protected function addTransactionSummary($transactions)
    {
        // Add section header
        $this->sheet->setCellValue("A{$this->currentRow}", 'TRANSACTION SUMMARY');
        $this->sheet->mergeCells("A{$this->currentRow}:H{$this->currentRow}");
        $this->styleSectionHeader($this->currentRow);

        $this->currentRow += 2;

        // Add headers
        $headers = ['Currency', 'Total Sales', 'Total Sales (EUR)', 'Declines', 'Refunds', 'Net Amount', 'Transaction Count'];
        foreach ($headers as $col => $header) {
            $this->sheet->setCellValueByColumnAndRow($col + 1, $this->currentRow, $header);
        }
        $this->styleTableHeader($this->currentRow);

        $this->currentRow++;

        // Add data
        foreach ($transactions as $currency => $data) {
            $row = [
                $currency,
                $data['total_sales'],
                $data['total_sales_eur'],
                $data['total_declines'],
                $data['total_refunds'],
                ($data['total_sales'] - $data['total_refunds']),
                $data['transaction_count']
            ];

            foreach ($row as $col => $value) {
                $cell = $this->sheet->getCellByColumnAndRow($col + 1, $this->currentRow);
                $cell->setValue($value);

                if ($col > 0) { // Skip currency column
                    $cell->getStyle()->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }
            }

            $this->currentRow++;
        }
    }

    protected function addFeesSection($fees)
    {
        $this->currentRow += 2;

        $this->sheet->setCellValue("A{$this->currentRow}", 'FEES');
        $this->sheet->mergeCells("A{$this->currentRow}:H{$this->currentRow}");
        $this->styleSectionHeader($this->currentRow);

        $this->currentRow += 2;

        // Add headers
        $headers = ['Fee Type', 'Amount (EUR)', 'Frequency'];
        foreach ($headers as $col => $header) {
            $this->sheet->setCellValueByColumnAndRow($col + 1, $this->currentRow, $header);
        }
        $this->styleTableHeader($this->currentRow);

        $this->currentRow++;

        // Add fees
        foreach ($fees as $fee) {
            $row = [
                $fee['type'],
                $fee['amount'],
                $fee['frequency']
            ];

            foreach ($row as $col => $value) {
                $cell = $this->sheet->getCellByColumnAndRow($col + 1, $this->currentRow);
                $cell->setValue($value);

                if ($col === 1) { // Amount column
                    $cell->getStyle()->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }
            }

            $this->currentRow++;
        }
    }

    protected function addRollingReserveSection($reserves, $releaseableReserves)
    {
        $this->currentRow += 2;

        $this->sheet->setCellValue("A{$this->currentRow}", 'ROLLING RESERVE');
        $this->sheet->mergeCells("A{$this->currentRow}:H{$this->currentRow}");
        $this->styleSectionHeader($this->currentRow);

        $this->currentRow += 2;

        // Current Period Reserve
        $headers = ['Currency', 'Original Amount', 'Reserve Amount (EUR)', 'Percentage', 'Release Date'];
        foreach ($headers as $col => $header) {
            $this->sheet->setCellValueByColumnAndRow($col + 1, $this->currentRow, $header);
        }
        $this->styleTableHeader($this->currentRow);

        $this->currentRow++;

        foreach ($reserves as $reserve) {
            $row = [
                $reserve['currency'],
                $reserve['original_amount'],
                $reserve['reserve_amount_eur'],
                $reserve['percentage'] . '%',
                $reserve['release_date']
            ];

            foreach ($row as $col => $value) {
                $cell = $this->sheet->getCellByColumnAndRow($col + 1, $this->currentRow);
                $cell->setValue($value);

                if ($col === 1 || $col === 2) { // Amount columns
                    $cell->getStyle()->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }
            }

            $this->currentRow++;
        }

        // Releaseable Reserve
        if (count($releaseableReserves) > 0) {
            $this->currentRow += 2;
            $this->sheet->setCellValue("A{$this->currentRow}", 'RELEASEABLE RESERVE');
            $this->sheet->mergeCells("A{$this->currentRow}:H{$this->currentRow}");
            $this->styleSectionHeader($this->currentRow);

            $this->currentRow += 2;

            $headers = ['Currency', 'Original Amount', 'Reserve Amount (EUR)', 'Reserve Date', 'Release Date'];
            foreach ($headers as $col => $header) {
                $this->sheet->setCellValueByColumnAndRow($col + 1, $this->currentRow, $header);
            }
            $this->styleTableHeader($this->currentRow);

            $this->currentRow++;

            foreach ($releaseableReserves as $reserve) {
                $row = [
                    $reserve->original_currency,
                    $reserve->original_amount,
                    $reserve->reserve_amount_eur,
                    $reserve->transaction_date,
                    $reserve->release_date
                ];

                foreach ($row as $col => $value) {
                    $cell = $this->sheet->getCellByColumnAndRow($col + 1, $this->currentRow);
                    $cell->setValue($value);

                    if ($col === 1 || $col === 2) { // Amount columns
                        $cell->getStyle()->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    }
                }

                $this->currentRow++;
            }
        }
    }

    protected function addTotalCalculations($settlementData)
    {
        $this->currentRow += 2;

        // Calculate totals
        $totalSalesEur = array_sum(array_column($settlementData['transactions'], 'total_sales_eur'));
        $totalFeesEur = array_sum(array_column($settlementData['fees'], 'amount'));
        $totalNewReserveEur = array_sum(array_column($settlementData['rolling_reserve'], 'reserve_amount_eur'));
        $totalReleaseableEur = array_sum(array_column($settlementData['releaseable_reserve'], 'reserve_amount_eur'));

        $rows = [
            ['Total Sales (EUR)', $totalSalesEur],
            ['Total Fees (EUR)', $totalFeesEur],
            ['New Rolling Reserve (EUR)', $totalNewReserveEur],
            ['Releaseable Reserve (EUR)', $totalReleaseableEur],
            ['Net Settlement Amount (EUR)', ($totalSalesEur - $totalFeesEur - $totalNewReserveEur + $totalReleaseableEur)]
        ];

        foreach ($rows as $row) {
            $this->sheet->setCellValue("A{$this->currentRow}", $row[0]);
            $this->sheet->setCellValue("B{$this->currentRow}", $row[1]);
            $this->sheet->getStyle("B{$this->currentRow}")->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

            if (array_key_last($rows) === array_search($row, $rows)) {
                $this->sheet->getStyle("A{$this->currentRow}:B{$this->currentRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'E0E0E0']
                    ]
                ]);
            }

            $this->currentRow++;
        }
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

        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer = new Xlsx($this->spreadsheet);
        $writer->save($path);

        return $path;
    }

    protected function styleSectionHeader($row)
    {
        $this->sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4472C4']
            ],
            'font' => [
                'color' => ['rgb' => 'FFFFFF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);
    }

    protected function styleTableHeader($row)
    {
        $this->sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font' => [
                'bold' => true
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'E0E0E0']
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);
    }
}
