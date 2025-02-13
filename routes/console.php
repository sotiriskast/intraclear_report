<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('intraclear:merchants-import')->daily()->timezone('Europe/Athens')->at('19:00')
    ->appendOutputTo(storage_path('logs/merchant-import.log'));
Schedule::command('intraclear:settlement-generate')
    ->weekly()
    ->tuesdays()
    ->timezone('Europe/Athens')
    ->at('00:00')
    ->appendOutputTo(storage_path('logs/settlement-reports.log'));
