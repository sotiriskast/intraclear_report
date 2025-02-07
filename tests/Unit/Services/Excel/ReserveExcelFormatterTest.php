<?php

namespace Tests\Unit\Services\Excel;

use App\Models\RollingReserveEntry;
use App\Services\DynamicLogger;
use App\Services\Excel\ReserveExcelFormatter;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\Eloquent\Collection;

class ReserveExcelFormatterTest extends TestCase
{
    private ReserveExcelFormatter $formatter;
    private Worksheet $worksheet;
    private DynamicLogger $logger;
    private int $currentRow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(DynamicLogger::class);
        $this->logger->shouldReceive('log')->byDefault()->andReturnNull();

        $this->formatter = new ReserveExcelFormatter($this->logger);

        $spreadsheet = new Spreadsheet();
        $this->worksheet = $spreadsheet->getActiveSheet();
        $this->currentRow = 1;
    }

    #[Test]
    public function it_formats_generated_reserves_with_single_model(): void
    {
        // Arrange
        $reserve = new RollingReserveEntry();
        $reserve->forceFill([
            'original_amount' => 10000,
            'original_currency' => 'USD',
            'reserve_amount_eur' => 8500,
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-31',
            'release_due_date' => '2025-07-31',
            'status' => 'pending',
            'exchange_rate' => 1.0
        ]);

        $currencyData = [
            'rolling_reserve' => $reserve,
            'currency' => 'USD'
        ];

        // Act
        $this->formatter->formatGeneratedReserves($this->worksheet, $currencyData, $this->currentRow);

        // Assert
        $this->assertEquals('Generated Reserve Details', $this->worksheet->getCell('A3')->getValue());
        $this->assertEquals('Type', $this->worksheet->getCell('A5')->getValue());
        $this->assertEquals('Rolling Reserve', $this->worksheet->getCell('A6')->getValue());
        $this->assertEquals(100.00, (float)$this->worksheet->getCell('E6')->getValue());
        $this->assertEquals(85.00, (float)$this->worksheet->getCell('F6')->getValue());
    }

    #[Test]
    public function it_formats_generated_reserves_with_collection(): void
    {
        // Arrange
        // Create actual model instances
        $reserve1 = new RollingReserveEntry();
        $reserve1->forceFill([
            'original_amount' => 10000,
            'original_currency' => 'USD',
            'reserve_amount_eur' => 8500,
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-31',
            'release_due_date' => '2025-07-31',
            'status' => 'pending',
            'exchange_rate' => 1.0
        ]);

        $reserve2 = new RollingReserveEntry();
        $reserve2->forceFill([
            'original_amount' => 20000,
            'original_currency' => 'USD',
            'reserve_amount_eur' => 17000,
            'period_start' => '2025-02-01',
            'period_end' => '2025-02-28',
            'release_due_date' => '2025-08-31',
            'status' => 'pending',
            'exchange_rate' => 1.0
        ]);

        // Create a proper Eloquent Collection, not a basic Collection
        $reserves = new Collection([$reserve1, $reserve2]);

        $currencyData = [
            'rolling_reserve' => $reserves,
            'currency' => 'USD'
        ];

        // Act
        $this->formatter->formatGeneratedReserves($this->worksheet, $currencyData, $this->currentRow);

        // Assert
        $this->assertEquals('Generated Reserve Details', $this->worksheet->getCell('A3')->getValue());
        $this->assertEquals('Type', $this->worksheet->getCell('A5')->getValue());
        $this->assertEquals('Rolling Reserve', $this->worksheet->getCell('A6')->getValue());
        $this->assertEquals(100.00, (float)$this->worksheet->getCell('E6')->getValue());
        $this->assertEquals(85.00, (float)$this->worksheet->getCell('F6')->getValue());
        $this->assertEquals('Rolling Reserve', $this->worksheet->getCell('A7')->getValue());
        $this->assertEquals(200.00, (float)$this->worksheet->getCell('E7')->getValue());
        $this->assertEquals(170.00, (float)$this->worksheet->getCell('F7')->getValue());
    }

    #[Test]
    public function it_logs_warning_when_no_reserve_data_found(): void
    {
        // Arrange
        $currencyData = [];

        // Expect logger to be called
        $this->logger->shouldReceive('log')
            ->once()
            ->with('warning', 'No rolling reserve data found', Mockery::any())
            ->andReturnNull();

        // Act
        $this->formatter->formatGeneratedReserves($this->worksheet, $currencyData, $this->currentRow);
    }
}
