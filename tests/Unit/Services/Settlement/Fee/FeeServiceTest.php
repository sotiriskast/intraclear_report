<?php

namespace Tests\Unit\Services\Settlement\Fee;

use App\Models\FeeType;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Services\Settlement\Fee\interfaces\CustomFeeHandlerInterface;
use App\Services\Settlement\Fee\interfaces\FeeFrequencyHandlerInterface;
use App\Services\Settlement\Fee\interfaces\StandardFeeHandlerInterface;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Services\Settlement\Fee\FeeService;
use App\Services\DynamicLogger;


class FeeServiceTest extends TestCase
{
    private FeeService $feeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->feeRepository = Mockery::mock(FeeRepositoryInterface::class);
        $this->logger = Mockery::mock(DynamicLogger::class);
        $this->frequencyHandler = Mockery::mock(FeeFrequencyHandlerInterface::class);
        $this->customFeeHandler = Mockery::mock(CustomFeeHandlerInterface::class);
        $this->standardFeeHandler = Mockery::mock(StandardFeeHandlerInterface::class);

        $this->feeService = new FeeService(
            $this->feeRepository,
            $this->logger,
            $this->frequencyHandler,
            $this->customFeeHandler,
            $this->standardFeeHandler
        );
    }

    private function setupLoggerExpectations(int $merchantId, int $standardFeesCount, int $customFeesCount): void
    {
        // Allow any info logging
        $this->logger
            ->shouldReceive('log')
            ->withArgs(function ($level, $message, $context) {
                return $level === 'info';
            })
            ->zeroOrMoreTimes();
        // Allow any error logging
        $this->logger
            ->shouldReceive('log')
            ->withArgs(function ($level, $message, $context) {
                return $level === 'error';
            })
            ->zeroOrMoreTimes();
        // Mock fee repository logging
        $this->feeRepository
            ->shouldReceive('logFeeApplication')
            ->zeroOrMoreTimes()
            ->andReturn(null);
    }
    #[Test]
    public function it_calculates_standard_fees_correctly(): void
    {
        // Arrange
        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 2,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];
        $dateRange = [
            'start' => '2025-01-01',
            'end' => '2025-01-31'
        ];
        $this->setupLoggerExpectations(1, 2, 0);
        $this->standardFeeHandler
            ->shouldReceive('getStandardFees')
            ->once()
            ->with(1, $transactionData)
            ->andReturn([
                [
                    'fee_type' => 'MDR Fee',
                    'fee_type_id' => 1,
                    'fee_amount' => 25.00,
                    'frequency' => 'transaction'
                ],
                [
                    'fee_type' => 'Transaction Fee',
                    'fee_type_id' => 2,
                    'fee_amount' => 0.70,
                    'frequency' => 'transaction'
                ]
            ]);
        $this->frequencyHandler
            ->shouldReceive('shouldApplyFee')
            ->twice()
            ->andReturn(true);

        $this->customFeeHandler
            ->shouldReceive('getCustomFees')
            ->once()
            ->with(1, $transactionData, $dateRange['start'])
            ->andReturn([]);

        // Act
        $fees = $this->feeService->calculateFees(1, $transactionData, $dateRange);

        // Assert
        $this->assertNotEmpty($fees);

        $mdrFee = collect($fees)->firstWhere('fee_type', 'MDR Fee');
        $this->assertNotNull($mdrFee);
        $this->assertEquals(25.00, $mdrFee['fee_amount']);

        $transactionFee = collect($fees)->firstWhere('fee_type', 'Transaction Fee');
        $this->assertNotNull($transactionFee);
        $this->assertEquals(0.70, $transactionFee['fee_amount']);
    }
    #[Test]
    public function it_handles_multiple_currencies_correctly(): void
    {
        // Arrange
        $transactionData = [
            'total_sales_eur' => 1200.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'USD',
            'exchange_rate' => 1.2
        ];

        $dateRange = [
            'start' => '2025-01-01',
            'end' => '2025-01-31'
        ];

        $this->setupLoggerExpectations(2, 1, 0);

        $this->standardFeeHandler
            ->shouldReceive('getStandardFees')
            ->once()
            ->with(2, $transactionData)
            ->andReturn([
                [
                    'fee_type' => 'MDR Fee',
                    'fee_type_id' => 1,
                    'fee_amount' => 30.00,
                    'frequency' => 'transaction'
                ]
            ]);

        $this->frequencyHandler
            ->shouldReceive('shouldApplyFee')
            ->once()
            ->andReturn(true);

        $this->customFeeHandler
            ->shouldReceive('getCustomFees')
            ->once()
            ->with(2, $transactionData, $dateRange['start'])
            ->andReturn([]);

        // Act
        $fees = $this->feeService->calculateFees(2, $transactionData, $dateRange);

        // Assert
        $mdrFee = collect($fees)->firstWhere('fee_type', 'MDR Fee');
        $this->assertNotNull($mdrFee);
        $this->assertEquals(30.00, $mdrFee['fee_amount']);
    }
    #[Test]
    public function it_handles_zero_transaction_amounts(): void
    {
        // Arrange
        $transactionData = [
            'total_sales_eur' => 0,
            'total_sales' => 0,
            'transaction_sales_count' => 0,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        $dateRange = [
            'start' => '2025-01-01',
            'end' => '2025-01-31'
        ];

        $this->setupLoggerExpectations(3, 2, 0);

        $this->standardFeeHandler
            ->shouldReceive('getStandardFees')
            ->once()
            ->with(3, $transactionData)
            ->andReturn([
                [
                    'fee_type' => 'Monthly Fee',
                    'fee_type_id' => 3,
                    'fee_amount' => 150.00,
                    'frequency' => 'monthly'
                ],
                [
                    'fee_type' => 'MDR Fee',
                    'fee_type_id' => 1,
                    'fee_amount' => 0,
                    'frequency' => 'transaction'
                ]
            ]);

        $this->frequencyHandler
            ->shouldReceive('shouldApplyFee')
            ->twice()
            ->andReturn(true);

        $this->customFeeHandler
            ->shouldReceive('getCustomFees')
            ->once()
            ->with(3, $transactionData, $dateRange['start'])
            ->andReturn([]);

        // Act
        $fees = $this->feeService->calculateFees(3, $transactionData, $dateRange);

        // Assert
        $this->assertNotEmpty($fees);

        $monthlyFee = collect($fees)->firstWhere('fee_type', 'Monthly Fee');
        $this->assertNotNull($monthlyFee);
        $this->assertEquals(150.00, $monthlyFee['fee_amount']);

        $mdrFee = collect($fees)->firstWhere('fee_type', 'MDR Fee');
        if ($mdrFee) {
            $this->assertEquals(0, $mdrFee['fee_amount']);
        }
    }

    #[Test]
    public function it_handles_invalid_merchant_gracefully(): void
    {
        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        $dateRange = [
            'start' => '2025-01-01',
            'end' => '2025-01-31'
        ];

        $this->standardFeeHandler
            ->shouldReceive('getStandardFees')
            ->once()
            ->with(99999, $transactionData)
            ->andThrow(new \Exception('Merchant not found'));

        $this->customFeeHandler
            ->shouldReceive('getCustomFees')
            ->never();

        $this->frequencyHandler
            ->shouldReceive('shouldApplyFee')
            ->never();

        $this->logger
            ->shouldReceive('log')
            ->once()
            ->withArgs(['error', 'Failed to calculate fees', Mockery::hasKey('error')])
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Merchant not found');

        $this->feeService->calculateFees(99999, $transactionData, $dateRange);
    }
}
