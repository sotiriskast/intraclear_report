<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_merchant_import_is_scheduled()
    {
        $schedule = app()->make(Schedule::class);

        $events = collect($schedule->events())->map(function ($event) {
            return [
                'command' => $event->command,
                'expression' => $event->expression,
                'timezone' => $event->timezone
            ];
        });

        $this->assertTrue($events->contains(function ($event) {
            return str_contains($event['command'], 'intraclear:merchants-import')
                && $event['timezone'] === 'Europe/Athens';
        }));
    }
}
