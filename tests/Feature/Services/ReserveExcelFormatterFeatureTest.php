<?php

namespace Tests\Feature\Services;

use App\Models\Merchant;
use App\Models\RollingReserveEntry;
use App\Services\Excel\ReserveExcelFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReserveExcelFormatterFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[test]
    public function it_generates_complete_reserve_report(): void
    {
        $merchant = Merchant::query()->create([
            'account_id' => 1,
            'name' => 'Test Merchant',
            'active' => true,
        ]);
        // Arrange
        $reserve = new RollingReserveEntry;
        $reserve->forceFill([
            'merchant_id' => $merchant->id,
            'original_amount' => 10000,
            'original_currency' => 'USD', // Added missing field
            'reserve_amount_eur' => 8500,
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-31',
            'release_due_date' => '2025-07-31',
            'exchange_rate' => '0.8',
            'status' => 'pending',
        ]);
        $reserve->save();

        $currencyData = [
            'currency' => 'USD',
            'rolling_reserve' => $reserve,
        ];

        $formatter = app(ReserveExcelFormatter::class);
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        $currentRow = 1;

        // Act
        $formatter->formatGeneratedReserves($worksheet, $currencyData, $currentRow);

        // Assert
        $this->assertEquals('Generated Reserve Details', $worksheet->getCell('A3')->getValue());
        $this->assertEquals('Rolling Reserve', $worksheet->getCell('A6')->getValue());
        $this->assertEquals(100.00, (float) $worksheet->getCell('E6')->getValue());
    }
}
