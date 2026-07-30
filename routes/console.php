<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notify:overdue-invoices')
    ->dailyAt('08:00')
    ->name('mark-overdue-invoices')
    ->withoutOverlapping(120)
    ->onOneServer();

Schedule::command('notify:due-tasks')
    ->dailyAt('08:30')
    ->name('notify-due-tasks')
    ->withoutOverlapping(120)
    ->onOneServer();

Schedule::command('notify:due-tickets')
    ->dailyAt('08:30')
    ->name('notify-due-tickets')
    ->withoutOverlapping(120)
    ->onOneServer();

Schedule::command('chatbot:sync-tables')
    ->hourly()
    ->name('chatbot-sync-tables')
    ->withoutOverlapping(55)
    ->onOneServer();

Schedule::command('social:sync-accounts')->hourly()->withoutOverlapping(55)->onOneServer();
Schedule::command('social:extend-tokens')->daily()->withoutOverlapping(120)->onOneServer();
Schedule::command('social:cleanup-media')->daily()->withoutOverlapping(120)->onOneServer();
Schedule::command('social:fail-stale-publications')->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command('social:refresh-agency-connections')->daily()->withoutOverlapping(120)->onOneServer();
Schedule::command('social:sync-post-publication-statuses')->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command('social:dispatch-due-publications')->everyMinute()->withoutOverlapping(5)->onOneServer();

Schedule::command('system:scheduler-heartbeat')->everyMinute()->withoutOverlapping(5)->onOneServer();
Schedule::command('system:dispatch-queue-heartbeats')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();
Schedule::command('system:prune-operational-logs')
    ->dailyAt('02:30')
    ->withoutOverlapping(120)
    ->onOneServer();
