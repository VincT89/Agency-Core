<?php

namespace App\Policies;

use App\Enums\Finance\InvoiceFiscalStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessFinance();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->canAccessFinance();
    }

    public function create(User $user): bool
    {
        return $user->canAccessFinance();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->canAccessFinance() && $invoice->isFiscalEditable();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->canAccessFinance()
            && $invoice->status === 'draft'
            && $invoice->isFiscalEditable()
            && blank($invoice->fiscal_number);
    }

    public function prepareFiscal(User $user, Invoice $invoice): bool
    {
        return $user->canAccessFinance() && $invoice->isFiscalEditable();
    }

    public function reopenFiscal(User $user, Invoice $invoice): bool
    {
        return $user->canAccessFinance()
            && $invoice->fiscal_status === InvoiceFiscalStatus::Ready;
    }
}
