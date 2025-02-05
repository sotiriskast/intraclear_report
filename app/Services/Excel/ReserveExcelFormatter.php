<?php

namespace App\Services\Excel;

use App\Models\RollingReserveEntry;
use App\Services\DynamicLogger;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment, NumberFormat};
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;
/**
 * Handles Excel formatting for reserve-related sections in settlement reports
 * Responsible for formatting both generated and released reserve details
 * with consistent styling and layout
 */
readonly class ReserveExcelFormatter
{
    /**
     * Initialize the reserve formatter
     *
     * @param DynamicLogger $logger Service for logging formatting operations
     */
    public function __construct(
        private DynamicLogger $logger
    )
    {
    }
    /**
     * Format the generated reserves section of the worksheet
     * Includes reserve amounts, periods, and release dates
     *
     * @param Worksheet $sheet Active worksheet
     * @param array $currencyData Currency-specific data containing reserve information
     * @param int &$currentRow Current row position in worksheet (passed by reference)
     * @return void
     */
    public function formatGeneratedReserves(
        Worksheet $sheet,
        array     $currencyData,
        int       &$currentRow
    ): void
    {
        $currentRow +=2;
        // Add section header
        $this->addSectionHeader($sheet, 'Generated Reserve Details', $currentRow);
        $currentRow++;
        $rollingReserve = $currencyData['rolling_reserve'] ?? null;
        if (!$rollingReserve) {
            $this->logger->log('warning', 'No rolling reserve data found', [
                'currency_data' => $currencyData
            ]);
            return;
        }

        // Move to next row for headers
        $currentRow++;

        // Add column headers
        $headers = ['Type', 'Percentage', 'Period Start', 'Period End', 'Original Amount', 'Reserve EUR', 'Release Date'];
        $this->addTableHeaders($sheet, $headers, $currentRow);

        // Convert to collection if single model
        $reserves = $rollingReserve instanceof Collection
            ? $rollingReserve
            : collect([$rollingReserve]);

        foreach ($reserves as $reserve) {
            $currentRow++;
            if (!$reserve instanceof RollingReserveEntry) {
                $this->logger->log('warning', 'Invalid reserve entry type', [
                    'type' => get_class($reserve)
                ]);
                continue;
            }
            $this->addReserveRow($sheet, $reserve, $currentRow);
        }

        $this->applyFormatting($sheet, $currentRow);
        $currentRow++;
    }
    /**
     * Format the released reserves section of the worksheet
     * Details reserves that have been released during the settlement period
     *
     * @param Worksheet $sheet Active worksheet
     * @param array $currencyData Currency-specific data containing released reserve information
     * @param int &$currentRow Current row position in worksheet (passed by reference)
     * @return void
     */
    public function formatReleasedReserves(
        Worksheet $sheet,
        array     $currencyData,
        int       &$currentRow
    ): void
    {
        $currentRow +=2;
        $this->addSectionHeader($sheet, 'Released Reserve Details', $currentRow);
        $currentRow ++;

              if (empty($currencyData['releaseable_reserve'])) {
            return;
        }

        $headers = ['Type', 'Period', 'Release Date','Exchange rate', 'Original Amount', 'Reserve EUR', 'Status'];
        $this->addTableHeaders($sheet, $headers, $currentRow);

        $currency = $currencyData['currency'] ?? null;
        if (!$currency) {
            $this->logger->log('warning', 'Currency not found in currency data', [
                'currency_data' => $currencyData
            ]);
            return;
        }

        foreach ($currencyData['releaseable_reserve'] as $reserve) {
            // Skip if currency doesn't match
            if ($reserve['original_currency'] !== $currency) {
                continue;
            }

            $currentRow++;
            $sheet->setCellValue('A' . $currentRow, 'Released Reserve');
            $sheet->setCellValue('B' . $currentRow,
                Carbon::parse($reserve['period_start'])->format('d/m/Y') . ' - ' .
                Carbon::parse($reserve['period_end'])->format('d/m/Y')
            );
            $sheet->setCellValue('C' . $currentRow, Carbon::parse($reserve['released_at'])->format('d/m/Y'));
            $sheet->setCellValue('D' . $currentRow, $reserve['exchange_rate']);
            $sheet->setCellValue('E' . $currentRow, $reserve['original_amount']);
            $sheet->setCellValue('F' . $currentRow, $reserve['reserve_amount_eur']);
            $sheet->setCellValue('G' . $currentRow, 'Released');

            $this->formatAmountCells($sheet, $currentRow);
        }
    }
    /**
     * Add a formatted section header to the worksheet
     *
     * @param Worksheet $sheet Active worksheet
     * @param string $title Header text
     * @param int &$row Current row position (passed by reference)
     * @return void
     */
    private function addSectionHeader(Worksheet $sheet, string $title, int &$row): void
    {
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:G{$row}");

        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        $sheet->getRowDimension($row)->setRowHeight(20);
    }
    /**
     * Add formatted column headers for a table section
     *
     * @param Worksheet $sheet Active worksheet
     * @param array $headers Array of header texts
     * @param int &$row Current row position (passed by reference)
     * @return void
     */
    private function addTableHeaders(Worksheet $sheet, array $headers, int &$row): void
    {
        foreach ($headers as $index => $header) {
            $column = chr(65 + $index); // Convert number to letter (0 = A, 1 = B, etc.)
            $sheet->setCellValue("{$column}{$row}", $header);

            $sheet->getStyle("{$column}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }

        $sheet->getRowDimension($row)->setRowHeight(20);
    }
    /**
     * Add a formatted row for a single reserve entry
     *
     * @param Worksheet $sheet Active worksheet
     * @param RollingReserveEntry $reserve Reserve entry to format
     * @param int $row Current row position
     * @return void
     */
    private function addReserveRow(Worksheet $sheet, \App\Models\RollingReserveEntry $reserve, int $row): void
    {
        $sheet->setCellValue("A{$row}", 'Rolling Reserve');
        $sheet->setCellValue("B{$row}", '10%');
        $sheet->setCellValue("C{$row}", Carbon::parse($reserve->period_start)->format('d/m/Y'));
        $sheet->setCellValue("D{$row}", Carbon::parse($reserve->period_end)->format('d/m/Y'));
        $sheet->setCellValue("E{$row}", $reserve->original_amount / 100);
        $sheet->setCellValue("F{$row}", $reserve->reserve_amount_eur / 100);
        $sheet->setCellValue("G{$row}", Carbon::parse($reserve->release_due_date)->format('d/m/Y'));

        // Apply number format to amount columns
        $sheet->getStyle("E{$row}:F{$row}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
    }
    /**
     * Apply consistent formatting to the entire reserve section
     * Includes column sizing, borders, and row heights
     *
     * @param Worksheet $sheet Active worksheet
     * @param int $lastRow Last row number of the section
     * @return void
     */
    private function applyFormatting(Worksheet $sheet, int $lastRow): void
    {
        // Auto-size columns
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Add borders
        $sheet->getStyle("A1:G{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Set row heights
        foreach (range(1, $lastRow) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    }
    /**
     * Format cells containing monetary amounts
     * Applies consistent number formatting with thousands separator and decimals
     *
     * @param Worksheet $sheet Active worksheet
     * @param int $row Row containing amount cells
     * @return void
     */
    private function formatAmountCells(Worksheet $sheet, int $row): void
    {
        $sheet->getStyle('E' . $row . ':F' . $row)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
    }
}
