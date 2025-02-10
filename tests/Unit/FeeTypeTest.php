<?php

namespace Tests\Unit;

use App\Models\FeeType;
use Tests\TestCase;

class FeeTypeTest extends TestCase
{
    public function test_can_create_fee_type(): void
    {
        $feeType = FeeType::create([
            'name' => 'Test Fee',
            'key' => 'test_fee',
            'frequency_type' => 'transaction',
            'is_percentage' => false,
        ]);

        $this->assertDatabaseHas('fee_types', [
            'name' => 'Test Fee',
            'key' => 'test_fee',
        ]);
    }
}
