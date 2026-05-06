<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }


    public function boot(): void
    {
        Event::listen(
            \App\Domain\Core\Events\TaskAssigned::class,
            \App\Domain\Core\Listeners\SendTaskAssignedNotification::class
        );
        Event::listen(
            \App\Domain\Core\Events\TicketAssigned::class,
            \App\Domain\Core\Listeners\SendTicketAssignedNotification::class
        );
        Event::listen(
            \App\Domain\Finance\Events\PaymentRecorded::class,
            \App\Domain\Finance\Listeners\SendPaymentRecordedNotification::class
        );
    }
}
