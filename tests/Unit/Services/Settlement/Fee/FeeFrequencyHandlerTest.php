<?php

namespace Tests\Unit\Services\Settlement\Fee;

use App\Repositories\FeeRepository;
use App\Services\Settlement\Fee\FeeFrequencyHandler;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeeFrequencyHandlerTest extends TestCase
{
    private FeeFrequencyHandler $handler;

    private FeeRepository $feeRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feeRepository = new FeeRepository(new \App\Repositories\MerchantRepository);
        $this->handler = new FeeFrequencyHandler($this->feeRepository);
    }

    #[Test]
    public function it_always_returns_true_for_transaction_frequency(): void
    {
        $result = $this->handler->shouldApplyFee(
            'transaction',
            1,
            1,
            ['start' => '2025-01-01', 'end' => '2025-01-31']
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function it_always_returns_true_for_daily_frequency(): void
    {
        $result = $this->handler->shouldApplyFee(
            'daily',
            1,
            1,
            ['start' => '2025-01-01', 'end' => '2025-01-31']
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function it_always_returns_true_for_weekly_frequency(): void
    {
        $result = $this->handler->shouldApplyFee(
            'weekly',
            1,
            1,
            ['start' => '2025-01-01', 'end' => '2025-01-31']
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_one_time_fee_not_previously_applied(): void
    {
        $result = $this->handler->shouldApplyFee(
            'one_time',
            1,
            1,
            ['start' => '2025-01-01', 'end' => '2025-01-31']
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_monthly_fee_in_first_week(): void
    {
        $date = ['start' => '2025-01-02', 'end' => '2025-01-31']; // First week of month

        $result = $this->handler->shouldApplyFee('monthly', 1, 1, $date);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_monthly_fee_not_in_first_week(): void
    {
        $date = ['start' => '2025-01-15', 'end' => '2025-01-31']; // Not in first week

        $result = $this->handler->shouldApplyFee('monthly', 1, 1, $date);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_handles_yearly_fee_in_first_week_of_year(): void
    {
        $date = ['start' => '2025-01-02', 'end' => '2025-01-31']; // First week of year

        $result = $this->handler->shouldApplyFee('yearly', 1, 1, $date);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_yearly_fee_not_in_first_week(): void
    {
        $date = ['start' => '2025-02-01', 'end' => '2025-02-28']; // Not in first week

        $result = $this->handler->shouldApplyFee('yearly', 1, 1, $date);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_returns_false_for_invalid_frequency_type(): void
    {
        $result = $this->handler->shouldApplyFee(
            'invalid_frequency',
            1,
            1,
            ['start' => '2025-01-01', 'end' => '2025-01-31']
        );

        $this->assertFalse($result);
    }
}
