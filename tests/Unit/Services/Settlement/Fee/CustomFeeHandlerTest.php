<?php

namespace Tests\Unit\Services\Settlement\Fee;

use Tests\TestCase;
use App\Services\Settlement\Fee\CustomFeeHandler;
use App\Services\DynamicLogger;
use App\Models\Merchant;
use App\Models\FeeType;
use App\Models\MerchantFee;
use App\Repositories\FeeRepository;

class CustomFeeHandlerTest extends TestCase
{
    private CustomFeeHandler $handler;
    private FeeRepository $feeRepository;
    private DynamicLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->feeRepository = new FeeRepository(new \App\Repositories\MerchantRepository());
        $this->logger = new DynamicLogger();
        $this->handler = new CustomFeeHandler($this->feeRepository, $this->logger);

        // Create necessary fee types
        FeeType::create([
            'name' => 'Custom Percentage Fee',
            'key' => 'custom_percentage',
            'frequency_type' => 'transaction',
            'is_percentage' => true,
        ]);

        FeeType::create([
            'name' => 'Custom Fixed Fee',
            'key' => 'custom_fixed',
            'frequency_type' => 'transaction',
            'is_percentage' => false,
        ]);
    }

    /** @test */
    public function it_calculates_custom_percentage_fee_correctly(): void
    {
        // Arrange
        $merchant = $this->createMerchantWithCustomFee(true, 250); // 2.50%

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        // Act
        $fees = $this->handler->getCustomFees($merchant->account_id, $transactionData, '2025-01-01');

        // Assert
        $this->assertCount(1, $fees);
        $this->assertEquals('Custom Percentage Fee', $fees[0]['fee_type']);
        $this->assertEquals('2.50%', $fees[0]['fee_rate']);
        $this->assertEquals(25.00, $fees[0]['fee_amount']);
    }

    /** @test */
    public function it_calculates_custom_fixed_fee_correctly(): void
    {
        // Arrange
        $merchant = $this->createMerchantWithCustomFee(false, 500); // 5.00 EUR

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        // Act
        $fees = $this->handler->getCustomFees($merchant->account_id, $transactionData, '2025-01-01');

        // Assert
        $this->assertCount(1, $fees);
        $this->assertEquals('Custom Fixed Fee', $fees[0]['fee_type']);
        $this->assertEquals('5.00', $fees[0]['fee_rate']);
        $this->assertEquals(5.00, $fees[0]['fee_amount']);
    }

    /** @test */
    public function it_returns_empty_array_for_inactive_custom_fees(): void
    {
        // Arrange
        $merchant = $this->createMerchantWithCustomFee(true, 0);

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        // Act
        $fees = $this->handler->getCustomFees($merchant->account_id, $transactionData, '2025-01-01');

        // Assert
        $this->assertEmpty($fees);
    }

    /** @test */
    public function it_handles_multiple_custom_fees(): void
    {
        // Arrange
        $merchant = Merchant::create([
            'account_id' => 1,
            'name' => 'Test Merchant',
            'email' => 'test@example.com',
            'active' => true
        ]);

        // Create two custom fees
        $this->createCustomFee($merchant, true, 250);  // 2.50%
        $this->createCustomFee($merchant, false, 500); // 5.00 EUR

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        // Act
        $fees = $this->handler->getCustomFees($merchant->account_id, $transactionData, '2025-01-01');

        // Assert
        $this->assertCount(2, $fees);
    }

    private function createMerchantWithCustomFee(bool $isPercentage, int $amount): Merchant
    {
        $merchant = Merchant::create([
            'account_id' => 1,
            'name' => 'Test Merchant',
            'email' => 'test@example.com',
            'active' => true
        ]);

        $this->createCustomFee($merchant, $isPercentage, $amount);

        return $merchant;
    }

    private function createCustomFee(Merchant $merchant, bool $isPercentage, int $amount): void
    {
        $feeType = FeeType::where([
            'is_percentage' => $isPercentage,
            'key' => $isPercentage ? 'custom_percentage' : 'custom_fixed'
        ])->first();

        MerchantFee::create([
            'merchant_id' => $merchant->id,
            'fee_type_id' => $feeType->id,
            'amount' => $amount,
            'active' => true
        ]);
    }
}
