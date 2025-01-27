<?php

namespace App\Observers;

use App\Models\RollingReserveEntry;
use Carbon\CarbonInterface;

class RollingReserveEntryObserver
{
    public function creating(RollingReserveEntry $entry): void
    {
        $entry->status ??= 'pending';
    }

    public function updating(RollingReserveEntry $entry): void
    {
        if ($entry->isDirty('status') && $entry->status === 'released') {
            $entry->released_at = now();
        }
    }
}
