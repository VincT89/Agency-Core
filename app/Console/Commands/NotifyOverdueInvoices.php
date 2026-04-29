<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use App\Enums\UserRole;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyOverdueInvoices extends Command
{

    protected $signature = 'notify:overdue-invoices';


    protected $description = 'Cambia lo stato delle fatture scadute in overdue e invia una notifica ricorrente (1 al giorno) ad admin e administration.';


    public function handle()
    {
        // 1. Marca come "scadute" (overdue) le fatture emesse che hanno superato la data di scadenza
        Invoice::query()
            ->where('status', 'issued')
            ->whereDate('due_date', '<', today())
            ->each(function (Invoice $invoice) {
                $invoice->status = 'overdue';
                $invoice->save();
            });

        // 2. Recupera tutte le fatture attualmente "overdue"
        $overdueInvoices = Invoice::where('status', 'overdue')->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('Nessuna fattura overdue trovata.');
            return;
        }

        // Recupero gli utenti notificabili (Admin e Administration)
        $notifiableUsers = User::whereIn('role', [UserRole::Admin, UserRole::Administration])
            ->where('status', 'active')
            ->get();

        if ($notifiableUsers->isEmpty()) {
            return;
        }

        $count = 0;

        foreach ($overdueInvoices as $invoice) {
            foreach ($notifiableUsers as $user) {
                // Deduplica: massimo 1 notifica al giorno per questo utente per questa specifica fattura
                $alreadyNotifiedToday = DB::table('notifications')
                    ->where('notifiable_id', $user->id)
                    ->where('notifiable_type', get_class($user))
                    ->where('data->type', 'invoice_overdue')
                    ->where('data->invoice_id', $invoice->id)
                    ->whereDate('created_at', today())
                    ->exists();

                if (!$alreadyNotifiedToday) {
                    $user->notify(new InvoiceOverdueNotification($invoice));
                    $count++;
                }
            }
        }

        $this->info("Inviate {$count} notifiche (deduplicate) per fatture overdue.");
    }
}
