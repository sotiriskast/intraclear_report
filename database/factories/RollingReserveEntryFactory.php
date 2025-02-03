<?php

namespace Database\Factories;

use App\Models\RollingReserveEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RollingReserveEntry>
 */
class RollingReserveEntryFactory extends Factory
{
    protected $model = RollingReserveEntry::class;

    public function definition(): array
    {
        return [
            'merchant_id' => 8027,
            'original_amount' => $this->faker->numberBetween(1000, 100000),
            'original_currency' => $this->faker->randomElement(['USD', 'JPY']),
            'reserve_amount_eur' => $this->faker->numberBetween(1000, 100000),
            'exchange_rate' => $this->faker->randomFloat(4, 0.8, 1.2),
            'period_start' => now(),
            'period_end' => now()->addWeek(),
            'release_due_date' => now()->addMonths(6),
            'status' => 'pending',
        ];
    }

    public function released(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'released',
                'released_at' => now(),
            ];
        });
    }
}
