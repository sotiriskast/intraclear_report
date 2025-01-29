<?php

namespace App\Observers;

use App\Models\RollingReserveEntry;

class RollingReserveEntryObserver
{
    //Observed rolling reserved and after creating add the status to pending
    public function creating(RollingReserveEntry $entry): void
    {
        $entry->status ??= 'pending';
    }
    //Observed rolling reserved and after updating add the status to released
    public function updating(RollingReserveEntry $entry): void
    {
        if ($entry->isDirty('status') && $entry->status === 'released') {
            $entry->released_at = now();
        }
    }
}
