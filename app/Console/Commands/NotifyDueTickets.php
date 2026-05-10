<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketDueSoonNotification;
use App\Notifications\TicketOverdueNotification;
use App\Services\Tickets\TicketNotificationRecipientResolver;
use App\Models\Scopes\ProjectSupremacyScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyDueTickets extends Command
{
    protected $signature = 'notify:due-tickets';

    protected $description = 'Notifica admin e assegnatari sui ticket in scadenza o scaduti';

    public function __construct(
        private TicketNotificationRecipientResolver $recipientResolver
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $dueSoonTickets = Ticket::withoutGlobalScope(ProjectSupremacyScope::class)
            ->open()
            ->dueSoon(1)
            ->with('assignee')
            ->get();

        $overdueTickets = Ticket::withoutGlobalScope(ProjectSupremacyScope::class)
            ->open()
            ->overdue()
            ->with('assignee')
            ->get();

        $countDueSoon = 0;
        $countOverdue = 0;

        foreach ($dueSoonTickets as $ticket) {
            foreach ($this->recipientResolver->recipientsFor($ticket) as $recipient) {
                if ($this->notifyOncePerDay($recipient, $ticket, TicketDueSoonNotification::class, 'ticket_due_soon')) {
                    $countDueSoon++;
                }
            }
        }

        foreach ($overdueTickets as $ticket) {
            foreach ($this->recipientResolver->recipientsFor($ticket) as $recipient) {
                if ($this->notifyOncePerDay($recipient, $ticket, TicketOverdueNotification::class, 'ticket_overdue')) {
                    $countOverdue++;
                }
            }
        }

        $this->info("Inviate {$countDueSoon} notifiche per ticket in scadenza.");
        $this->info("Inviate {$countOverdue} notifiche per ticket scaduti.");
    }

    private function notifyOncePerDay(User $user, Ticket $ticket, string $notificationClass, string $type): bool
    {
        $alreadyNotifiedToday = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->where('data->type', $type)
            ->where('data->ticket_id', $ticket->id)
            ->whereDate('created_at', today())
            ->exists();

        if (!$alreadyNotifiedToday) {
            $user->notify(new $notificationClass($ticket));
            return true;
        }

        return false;
    }
}
