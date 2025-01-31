<?php

namespace Tests\Unit\Services\Settlement\Fee;

use Tests\TestCase;
use App\Services\Settlement\Fee\FeeService;
use App\Services\DynamicLogger;
use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\FeeType;
use App\Models\MerchantFee;

class FeeServiceTest extends TestCase
{
    private FeeService $feeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->feeService = app(FeeService::class);

        // Set up common fee types
        FeeType::create([
            'name' => 'MDR Fee',
            'key' => 'mdr_fee',
            'frequency_type' => 'transaction',
            'is_percentage' => true,
        ]);

        FeeType::create([
            'name' => 'Transaction Fee',
            'key' => 'transaction_fee',
            'frequency_type' => 'transaction',
            'is_percentage' => false,
        ]);

        FeeType::create([
            'name' => 'Monthly Fee',
            'key' => 'monthly_fee',
            'frequency_type' => 'monthly',
            'is_percentage' => false,
        ]);
    }

    /** @test */
    public function it_calculates_standard_fees_correctly(): void
    {
        // Arrange
        $merchant = Merchant::create([
            'account_id' => 1,
            'name' => 'Test Merchant',
            'email' => 'test@example.com',
            'active' => true
        ]);

        MerchantSetting::create([
            'merchant_id' => $merchant->id,
            'mdr_percentage' => 250, // 2.50%
            'transaction_fee' => 35,  // 0.35 EUR
            'monthly_fee' => 15000,   // 150.00 EUR
            'rolling_reserve_percentage' => 1000,
            'holding_period_days' => 180
        ]);

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

        // Act
        $fees = $this->feeService->calculateFees($merchant->account_id, $transactionData, $dateRange);

        // Assert
        $this->assertNotEmpty($fees);

        $mdrFee = collect($fees)->firstWhere('fee_type', 'MDR Fee');
        $this->assertNotNull($mdrFee);
        $this->assertEquals(25.00, $mdrFee['fee_amount']); // 2.50% of 1000

        $transactionFee = collect($fees)->firstWhere('fee_type', 'Transaction Fee');
        $this->assertNotNull($transactionFee);
        $this->assertEquals(0.70, $transactionFee['fee_amount']); // 0.35 * 2 transactions
    }

    /** @test */
    public function it_handles_multiple_currencies_correctly(): void
    {
        // Arrange
        $merchant = Merchant::create([
            'account_id' => 2,
            'name' => 'Test Merchant 2',
            'email' => 'test2@example.com',
            'active' => true
        ]);

        MerchantSetting::create([
            'merchant_id' => $merchant->id,
            'mdr_percentage' => 250,
            'transaction_fee' => 35,
            'monthly_fee' => 15000,
            'rolling_reserve_percentage' => 1000,
            'holding_period_days' => 180
        ]);

        $transactionData = [
            'total_sales_eur' => 1200.00, // Converted amount
            'total_sales' => 1000.00,     // Original amount
            'transaction_sales_count' => 1,
            'currency' => 'USD',
            'exchange_rate' => 1.2
        ];

        $dateRange = [
            'start' => '2025-01-01',
            'end' => '2025-01-31'
        ];

        // Act
        $fees = $this->feeService->calculateFees($merchant->account_id, $transactionData, $dateRange);

        // Assert
        $mdrFee = collect($fees)->firstWhere('fee_type', 'MDR Fee');
        $this->assertNotNull($mdrFee);
        $this->assertEquals(30.00, $mdrFee['fee_amount']); // 2.50% of 1200 EUR
    }

    /** @test */
    public function it_handles_zero_transaction_amounts(): void
    {
        // Arrange
        $merchant = Merchant::create([
            'account_id' => 3,
            'name' => 'Test Merchant 3',
            'email' => 'test3@example.com',
            'active' => true
        ]);

        MerchantSetting::create([
            'merchant_id' => $merchant->id,
            'mdr_percentage' => 250,
            'transaction_fee' => 35,
            'monthly_fee' => 15000,
            'rolling_reserve_percentage' => 1000,
            'holding_period_days' => 180
        ]);

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

        // Act
        $fees = $this->feeService->calculateFees($merchant->account_id, $transactionData, $dateRange);

        // Assert
        $this->assertNotEmpty($fees);

        // Should still include monthly fee even with zero transactions
        $monthlyFee = collect($fees)->firstWhere('fee_type', 'Monthly Fee');
        $this->assertNotNull($monthlyFee);
        $this->assertEquals(150.00, $monthlyFee['fee_amount']);

        // MDR fee should be zero
        $mdrFee = collect($fees)->firstWhere('fee_type', 'MDR Fee');
        if ($mdrFee) {
            $this->assertEquals(0, $mdrFee['fee_amount']);
        }
    }

    /** @test */
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

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->feeService->calculateFees(99999, $transactionData, $dateRange);
    }
}
