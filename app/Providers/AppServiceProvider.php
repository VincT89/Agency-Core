<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;
use App\Models\{Ticket, Invoice, Payment, CalendarEvent, Client, Project, Task, Attachment};
use App\Policies\{TicketPolicy, InvoicePolicy, PaymentPolicy, CalendarEventPolicy,
                  ClientPolicy, ProjectPolicy, TaskPolicy, AttachmentPolicy};
use App\Observers\{TicketObserver, InvoiceObserver, PaymentObserver, CalendarEventObserver, ClientObserver, ProjectObserver, TaskObserver, AttachmentObserver, UserObserver};

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }


    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.custom');
        \Illuminate\Support\Facades\Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        Gate::policy(Ticket::class,        TicketPolicy::class);
        Gate::policy(Invoice::class,       InvoicePolicy::class);
        Gate::policy(Payment::class,       PaymentPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::policy(Client::class,        ClientPolicy::class);
        Gate::policy(Project::class,       ProjectPolicy::class);
        Gate::policy(Task::class,          TaskPolicy::class);
        Gate::policy(Attachment::class,    AttachmentPolicy::class);

        // Shooting Policies
        Gate::policy(\App\Models\Shooting\Shoot::class, \App\Policies\ShootPolicy::class);

        Gate::define('system.admin', function (\App\Models\User $user) {
            return $user->canManageSystem();
        });

        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            if (auth()->check()) {
                $user = auth()->user();

                $counts = \Illuminate\Support\Facades\Cache::remember('sidebar_counts_' . $user->id, 60, function () {
                    return [
                        'clientsCount'    => \App\Models\Client::where('status', 'active')->count(),
                        'projectsCount'   => \App\Models\Project::where('status', 'active')->count(),
                        'openTickets'     => \App\Models\Ticket::whereIn('status', ['open', 'in_progress'])->count(),
                        'overdueInvoices' => \App\Models\Invoice::where('status', 'overdue')->count(),
                        'openTasks'       => \App\Models\Task::open()->count(),
                    ];
                });

                $view->with([
                    'clientsCount'    => $counts['clientsCount'],
                    'projectsCount'   => $counts['projectsCount'],
                    'openTickets'     => $counts['openTickets'],
                    'overdueInvoices' => $counts['overdueInvoices'],
                    'openTasks'       => $counts['openTasks'],
                    'unreadNotificationsCount' => $user->unreadNotifications()->count(),
                    'latestNotifications'      => $user->notifications()->latest()->limit(5)->get(),
                ]);
            }
        });
    }
}
