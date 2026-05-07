<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notify:overdue-invoices')
    ->dailyAt('08:00')
    ->name('mark-overdue-invoices')
    ->withoutOverlapping();

Schedule::command('notify:due-tasks')
    ->dailyAt('08:30')
    ->name('notify-due-tasks')
    ->withoutOverlapping();


Schedule::command('chatbot:sync-tables')
    ->hourly()
    ->name('chatbot-sync-tables')
    ->withoutOverlapping();
