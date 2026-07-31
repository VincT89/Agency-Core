<?php

namespace App\Notifications;

use App\Enums\Finance\ElectronicInvoiceTransmissionStatus;
use App\Models\Invoice;
use Illuminate\Notifications\Notification;

class ElectronicInvoiceStatusNotification extends Notification
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly ElectronicInvoiceTransmissionStatus $status,
        public readonly ?string $detail = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'electronic_invoice_status',
            'category' => 'finance',
            'title' => 'Fattura elettronica: '.$this->status->label(),
            'message' => $this->message(),
            'url' => route('invoices.show', $this->invoice),
            'invoice_id' => $this->invoice->getKey(),
            'status' => $this->status->value,
        ];
    }

    private function message(): string
    {
        $clientName = $this->invoice->client?->company_name
            ?: $this->invoice->client?->name
            ?: 'il cliente';
        $message = "La fattura {$this->invoice->fiscal_number} per {$clientName} è ora: {$this->status->label()}.";

        return filled($this->detail)
            ? $message.' '.$this->detail
            : $message;
    }
}
