<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class InvoiceOverdueNotification extends Notification
{
    public function __construct(public readonly Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'invoice_overdue',
            'title'   => 'Fattura scaduta',
            'message' => "La fattura {$this->invoice->number} risulta scaduta e insoluta.",
            'url'     => route('invoices.show', $this->invoice),
            'invoice_id' => $this->invoice->id,
        ];
    }
}
