<?php

namespace Tests\Unit\Services\Settlement\Fee;

use App\Models\FeeType;
use App\Models\MerchantFee;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\CustomFeeHandler;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomFeeHandlerTest extends TestCase
{
    private CustomFeeHandler $handler;

    private FeeRepositoryInterface $feeRepository;

    private DynamicLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks instead of real instances
        $this->feeRepository = Mockery::mock(FeeRepositoryInterface::class);
        $this->logger = Mockery::mock(DynamicLogger::class);

        // Allow logger to receive any log calls
        $this->logger->shouldReceive('log')->andReturn(null);

        $this->handler = new CustomFeeHandler($this->feeRepository, $this->logger);
    }

    #[Test]
    public function it_calculates_custom_percentage_fee_correctly(): void
    {
        // Create test data
        $feeType = new FeeType([
            'id' => 1,
            'name' => 'Custom Percentage Fee',
            'key' => 'custom_percentage',
            'frequency_type' => 'transaction',
            'is_percentage' => true,
        ]);

        $merchantFee = new MerchantFee([
            'id' => 1,
            'merchant_id' => 1,
            'fee_type_id' => 1,
            'amount' => 250, // 2.50%
            'active' => true,
        ]);

        $merchantFee->setRelation('feeType', $feeType);

        // Setup repository expectations
        $this->feeRepository
            ->shouldReceive('getMerchantFees')
            ->once()
            ->with(1, '2025-01-01')
            ->andReturn(collect([$merchantFee]));

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'EUR',
            'exchange_rate' => 1.0,
        ];

        // Act
        $fees = $this->handler->getCustomFees(1, $transactionData, '2025-01-01');

        // Assert
        $this->assertCount(1, $fees);
        $this->assertEquals('Custom Percentage Fee', $fees[0]['fee_type']);
        $this->assertEquals('2.50%', $fees[0]['fee_rate']);
        $this->assertEquals(25.00, $fees[0]['fee_amount']);
    }

    #[Test]
    public function it_calculates_custom_fixed_fee_correctly(): void
    {
        // Create test data
        $feeType = new FeeType([
            'id' => 2,
            'name' => 'Custom Fixed Fee',
            'key' => 'custom_fixed',
            'frequency_type' => 'transaction',
            'is_percentage' => false,
        ]);

        $merchantFee = new MerchantFee([
            'id' => 2,
            'merchant_id' => 1,
            'fee_type_id' => 2,
            'amount' => 500, // 5.00 EUR
            'active' => true,
        ]);

        $merchantFee->setRelation('feeType', $feeType);

        // Setup repository expectations
        $this->feeRepository
            ->shouldReceive('getMerchantFees')
            ->once()
            ->with(1, '2025-01-01')
            ->andReturn(collect([$merchantFee]));

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'EUR',
            'exchange_rate' => 1.0,
        ];

        // Act
        $fees = $this->handler->getCustomFees(1, $transactionData, '2025-01-01');

        // Assert
        $this->assertCount(1, $fees);
        $this->assertEquals('Custom Fixed Fee', $fees[0]['fee_type']);
        $this->assertEquals('5.00', $fees[0]['fee_rate']);
        $this->assertEquals(5.00, $fees[0]['fee_amount']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
