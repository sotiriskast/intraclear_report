<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\FeeType;

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
            'key' => 'test_fee'
        ]);
    }
}
