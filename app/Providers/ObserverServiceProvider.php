<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\{Ticket, Invoice, Payment, CalendarEvent, Client, Project, Task, Attachment, MarketingCampaign, MarketingCampaignPost};
use App\Observers\{TicketObserver, InvoiceObserver, PaymentObserver, CalendarEventObserver, ClientObserver, ProjectObserver, TaskObserver, AttachmentObserver, UserObserver, MarketingCampaignObserver, MarketingCampaignPostObserver};

class ObserverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }


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
        MarketingCampaign::observe(MarketingCampaignObserver::class);
        MarketingCampaignPost::observe(MarketingCampaignPostObserver::class);
    }
}
