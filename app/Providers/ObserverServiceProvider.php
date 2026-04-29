<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\{Ticket, Invoice, Payment, CalendarEvent, Client, Project, Task, Attachment};
use App\Observers\{TicketObserver, InvoiceObserver, PaymentObserver, CalendarEventObserver, ClientObserver, ProjectObserver, TaskObserver, AttachmentObserver, UserObserver};

class ObserverServiceProvider extends ServiceProvider
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
        Ticket::observe(TicketObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);
        CalendarEvent::observe(CalendarEventObserver::class);
        Client::observe(ClientObserver::class);
        Project::observe(ProjectObserver::class);
        Task::observe(TaskObserver::class);
        Attachment::observe(AttachmentObserver::class);
        \App\Models\User::observe(UserObserver::class);
        \App\Models\Shooting\Shoot::observe(\App\Observers\ShootObserver::class);
    }
}
