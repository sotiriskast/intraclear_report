<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function test_merchant_import_is_scheduled_to_runs_once_a_day()
    {
        $this->travelTo(now()->endOfDay()->setTime(07, 00));
        $this->artisan('schedule:run');
    }
    #[Test]
    public function test_merchant_import_command_is_scheduled()
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events());

        $this->assertTrue(
            $events->contains(function ($event) {
                return str_contains($event->command, 'intraclear:merchants-import') &&
                    $event->expression === '0 19 * * *' &&
                    $event->timezone === 'Europe/Athens';
            }),
            'The scheduled command "intraclear:merchants-import" was not found in the schedule.'
        );
    }
}
