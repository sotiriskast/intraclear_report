<?php

namespace Tests\Unit\Services\Settlement\Fee;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\FeeType;
use App\Services\Settlement\Fee\StandardFeeHandler;
use App\Services\DynamicLogger;
use App\Repositories\MerchantSettingRepository;
use App\Repositories\MerchantRepository;

class StandardFeeHandlerTest extends TestCase
{
    private StandardFeeHandler $standardFeeHandler;
    private MerchantSettingRepository $merchantSettingRepository;
    private MerchantRepository $merchantRepository;
    private DynamicLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchantSettingRepository = new MerchantSettingRepository();
        $this->merchantRepository = new MerchantRepository();
        $this->logger = new DynamicLogger();

        $this->standardFeeHandler = new StandardFeeHandler(
            $this->merchantSettingRepository,
            $this->merchantRepository,
            $this->logger
        );

        // Create fee types needed for testing
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

    #[Test]
    public function it_calculates_mdr_fee_correctly(): void
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
            'transaction_fee' => 0,
            'monthly_fee' => 0,
            'rolling_reserve_percentage' => 1000,
            'holding_period_days' => 180
        ]);

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        // Act
        $fees = $this->standardFeeHandler->getStandardFees($merchant->account_id, $transactionData);

        // Assert
        $this->assertCount(1, $fees);
        $this->assertEquals('MDR Fee', $fees[0]['fee_type']);
        $this->assertEquals('2.50%', $fees[0]['fee_rate']);
        $this->assertEquals(25.00, $fees[0]['fee_amount']); // 2.50% of 1000
    }

    #[Test]
    public function it_calculates_transaction_fee_correctly(): void
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
            'mdr_percentage' => 0,
            'transaction_fee' => 35, // 0.35 EUR per transaction
            'monthly_fee' => 0,
            'rolling_reserve_percentage' => 1000,
            'holding_period_days' => 180
        ]);

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 5, // 5 transactions
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        // Act
        $fees = $this->standardFeeHandler->getStandardFees($merchant->account_id, $transactionData);

        // Assert
        $this->assertCount(1, $fees);
        $this->assertEquals('Transaction Fee', $fees[0]['fee_type']);
        $this->assertEquals('0.35', $fees[0]['fee_rate']);
        $this->assertEquals(1.75, $fees[0]['fee_amount']); // 0.35 * 5 transactions
    }

    #[Test]
    public function it_returns_empty_array_when_no_fees_configured(): void
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
            'mdr_percentage' => 0,
            'transaction_fee' => 0,
            'monthly_fee' => 0,
            'rolling_reserve_percentage' => 1000,
            'holding_period_days' => 180
        ]);

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 1,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        // Act
        $fees = $this->standardFeeHandler->getStandardFees($merchant->account_id, $transactionData);

        // Assert
        $this->assertEmpty($fees);
    }

    #[Test]
    public function it_calculates_multiple_fees_correctly(): void
    {
        // Arrange
        $merchant = Merchant::create([
            'account_id' => 4,
            'name' => 'Test Merchant 4',
            'email' => 'test4@example.com',
            'active' => true
        ]);

        MerchantSetting::create([
            'merchant_id' => $merchant->id,
            'mdr_percentage' => 250, // 2.50%
            'transaction_fee' => 35, // 0.35 per transaction
            'monthly_fee' => 0,
            'rolling_reserve_percentage' => 1000,
            'holding_period_days' => 180
        ]);

        $transactionData = [
            'total_sales_eur' => 1000.00,
            'total_sales' => 1000.00,
            'transaction_sales_count' => 5,
            'currency' => 'EUR',
            'exchange_rate' => 1.0
        ];

        // Act
        $fees = $this->standardFeeHandler->getStandardFees($merchant->account_id, $transactionData);

        // Assert
        $this->assertCount(2, $fees);

        $mdrFee = collect($fees)->firstWhere('fee_type', 'MDR Fee');
        $this->assertNotNull($mdrFee);
        $this->assertEquals(25.00, $mdrFee['fee_amount']); // 2.50% of 1000

        $transactionFee = collect($fees)->firstWhere('fee_type', 'Transaction Fee');
        $this->assertNotNull($transactionFee);
        $this->assertEquals(1.75, $transactionFee['fee_amount']); // 0.35 * 5
    }

    #[Test]
    public function it_handles_invalid_merchant_gracefully(): void
    {
        // Act
        $fees = $this->standardFeeHandler->getStandardFees(999999, []);

        // Assert
        $this->assertEmpty($fees);
    }
}
