<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\AuditLogService;

class InvoiceObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Invoice $invoice): void
    {
        $this->auditLog->log('created', $invoice, null, $invoice->getAttributes());
    }

    public function updated(Invoice $invoice): void
    {
        $old = array_intersect_key($invoice->getOriginal(), $invoice->getDirty());
        $new = $invoice->getDirty();

        $action = isset($new['status']) ? 'status_changed' : 'updated';

        $this->auditLog->log($action, $invoice, $old, $new);
    }

    public function deleted(Invoice $invoice): void
    {
        $this->auditLog->log('deleted', $invoice, $invoice->getOriginal(), null);
    }
}
