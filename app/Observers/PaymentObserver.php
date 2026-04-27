<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\AuditLogService;

class PaymentObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Payment $payment): void
    {
        if ($payment->invoice) {
            /** @var \App\Models\User|null $user */
            $user = auth()->user();
            $userName = $user?->name ?? 'Sistema';
            $amt = number_format((float) $payment->amount, 2, ',', '.');
            $desc = "{$userName} ha registrato un pagamento di €{$amt} sulla fattura {$payment->invoice->number}";
            $this->auditLog->log('registered_payment', $payment->invoice, null, null, $desc);
        }
    }

    public function updated(Payment $payment): void
    {
        $old = array_intersect_key($payment->getOriginal(), $payment->getDirty());
        $new = $payment->getDirty();

        $this->auditLog->log('updated', $payment, $old, $new);
    }

    public function deleted(Payment $payment): void
    {
        if ($payment->invoice) {
            /** @var \App\Models\User|null $user */
            $user = auth()->user();
            $userName = $user?->name ?? 'Sistema';
            $amt = number_format((float) $payment->amount, 2, ',', '.');
            $desc = "{$userName} ha eliminato un pagamento di €{$amt} dalla fattura {$payment->invoice->number}";
            $this->auditLog->log('deleted_payment', $payment->invoice, null, null, $desc);
        }
    }
}
