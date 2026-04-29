<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
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
        Event::listen(
            \App\Events\SocialPostApprovedByClient::class,
            \App\Listeners\GenerateTaskForApprovedSocialPost::class
        );
        Event::listen(
            \App\Events\EditorialPlanApprovedByClient::class,
            \App\Listeners\GenerateTasksForApprovedEditorialPlan::class
        );
        Event::listen(
            \App\Events\EditorialSlotPublished::class,
            \App\Listeners\CloseTaskWhenSocialPostPublished::class
        );
    }
}
