<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('pixelforge:sync')->everyMinute()->withoutOverlapping();

// Records queue throughput and wait times for Horizon's metrics page.
Schedule::command('horizon:snapshot')->everyFiveMinutes();
