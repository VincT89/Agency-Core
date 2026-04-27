<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentRecordedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'payment_recorded',
            'title'   => 'Pagamento registrato',
            'message' => "È stato registrato un pagamento di € ".number_format($this->payment->amount, 2, ',', '.')." per la fattura {$this->payment->invoice->number}.",
            'url'     => route('invoices.show', $this->payment->invoice_id), // Puntiamo alla fattura che contiene i pagamenti
            'invoice_id' => $this->payment->invoice_id,
        ];
    }
}
