<?php

namespace App\Services\Excel\Formatter;

use App\Services\Excel\Calculator\SummaryCalculator;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Formatter service for Excel summary sections
 *
 * This service handles:
 * - Formatting summary sections in Excel reports
 * - Creating standardized layouts
 * - Applying consistent styling
 * - Calculating and displaying totals
 *
 * @property SummaryCalculator $calculator Service for calculating summary values
 */
readonly class SummaryExcelFormater
{
    public function __construct(
        private SummaryCalculator $calculator
    )
    {
    }

    /**
     * Format the total summary section in the worksheet
     *
     * @param Worksheet $sheet Active worksheet
     * @param array $currencyData Currency-specific data
     * @param int &$currentRow Current row position (passed by reference)
     */
    public function formatTotalSummary(Worksheet $sheet, array $currencyData, int &$currentRow): void
    {
        $currentRow += 2;
        $this->addSectionHeader('Summary', $currentRow, $sheet);
        $currentRow++;
        $sheet->setCellValue('F' . $currentRow, 'Total ' . $currencyData['currency']);
        $sheet->getStyle('F' . $currentRow)->getFont()->setBold(true);
        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue('G' . $currentRow, 'Total EUR');
        $sheet->getStyle('G' . $currentRow)->getFont()->setBold(true);
        $currentRow++;
        $summaryItems = $this->getSummaryItems($currencyData);

        foreach ($summaryItems as $item) {
            $this->addSummaryRow($sheet, $currentRow, $item);
            $currentRow++;
        }

        $this->formatSummarySection($sheet, $currentRow - count($summaryItems), $currentRow);
    }

    /**
     * Add section header with consistent styling
     *
     * @param string $title Header title
     * @param int &$currentRow Current row position
     * @param Worksheet $currentSheet Active worksheet
     */
    private function addSectionHeader(string $title, &$currentRow, $currentSheet): void
    {
        $currentSheet->setCellValue('A' . $currentRow, $title);
        $currentSheet->mergeCells('A' . $currentRow . ':G' . $currentRow);
        $currentSheet->getStyle('A' . $currentRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4472C4'],
            ],
        ]);
    }

    /**
     * Get summary items with calculated values
     *
     * @param array $currencyData Currency-specific data
     * @return array Array of summary items with labels and values
     */
    private function getSummaryItems(array $currencyData): array
    {
        $currency = $currencyData['currency'] ?? '';

        return [
            ['Total Processing Amount',
                $this->calculator->getTotalProcessingAmount($currencyData),
                $this->calculator->getTotalProcessingAmountEur($currencyData)
            ],
            ['Total Intraclear Fees',
                $this->calculator->getTotalFees($currencyData),
                $this->calculator->getTotalFeesEur($currencyData)
            ],
            ['Total Chargeback',
                $this->calculator->getTotalChargebacks($currencyData),
                $this->calculator->getTotalChargebacksEur($currencyData)
            ],
            ['Total Refund',
                $this->calculator->getTotalRefund($currencyData),
                $this->calculator->getTotalRefundEur($currencyData)
            ],
            ['Generated Reserve',
                $this->calculator->getGeneratedReserve($currencyData),
                $this->calculator->getGeneratedReserveEur($currencyData)
            ],
            ['Gross Amount',
                $this->calculator->getGrossAmount($currencyData),
                $this->calculator->getGrossAmountEur($currencyData)
            ],
//            ['Miscellaneous Adjustment', 0, 0],
//            ['Previous Balance Amount', null, 0],
            ['Rolling reserve amount released',
                $this->calculator->getReleasedReserve($currencyData),
                $this->calculator->getReleasedReserveEur($currencyData)
            ],
            ["Statement Total ",
                $this->calculator->getStatementTotal($currencyData),
                $this->calculator->getStatementTotalEur($currencyData)
            ],
//            ["Previous Balance Amount {$currency}", null, null],
            ["Total Amount {$currency}",
                $this->calculator->getTotalAmount($currencyData),
                $this->calculator->getTotalAmountEur($currencyData)
            ],
            ["Foreign Exchange fee {$currency}",
                $this->calculator->getFxFee($currencyData),
                $this->calculator->getFxFeeEur($currencyData)
            ],
            ['', null, null],
            ['Total Amount Paid',
                $this->calculator->getTotalAmountPaid($currencyData),
                $this->calculator->getTotalAmountPaidEur($currencyData)
            ],
        ];
    }

    private function addSummaryRow(Worksheet $sheet, int $row, array $item): void
    {
        [$label, $originalAmount, $eurAmount] = $item;

        $sheet->setCellValue("A{$row}", $label);
        $sheet->mergeCells("A{$row}:E{$row}");

        if ($originalAmount !== null) {
            $sheet->setCellValue("F{$row}", $originalAmount);
        }

        if ($eurAmount !== null) {
            $sheet->setCellValue("G{$row}", $eurAmount);
        }
    }

    private function formatSummarySection(Worksheet $sheet, int $startRow, int $endRow): void
    {
        $sheet->getStyle("A{$startRow}:G{$endRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }
}
