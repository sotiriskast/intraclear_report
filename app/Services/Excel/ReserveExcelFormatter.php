<?php

namespace App\Services\Excel;

use App\Models\RollingReserveEntry;
use App\Services\DynamicLogger;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment, NumberFormat};
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

readonly class ReserveExcelFormatter
{
    public function __construct(
        private DynamicLogger $logger
    )
    {
    }

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

    private function formatAmountCells(Worksheet $sheet, int $row): void
    {
        $sheet->getStyle('E' . $row . ':F' . $row)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
    }
}
