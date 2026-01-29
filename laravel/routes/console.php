<?php

use App\Console\Commands\AutoCheckLeaveCommand;
use App\Console\Commands\MarkAbsent;
use App\Console\Commands\UpdateOverdueMissions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(MarkAbsent::class)->cron('0 */4 * * *') 
        ->appendOutputTo(storage_path('logs/attendance.log'))
        ->withoutOverlapping();

Schedule::command(AutoCheckLeaveCommand::class)->daily()
        ->appendOutputTo(storage_path('logs/leave_check.log'))
        ->withoutOverlapping();
Schedule::command(UpdateOverdueMissions::class)->daily()
        ->appendOutputTo(storage_path('logs/auto-check-overdue.log'))
        ->withoutOverlapping();

// Schedule::command(MarkAbsent::class)->everyMinute()
//         ->appendOutputTo(storage_path('logs/attendance.log'))
//         ->withoutOverlapping();

