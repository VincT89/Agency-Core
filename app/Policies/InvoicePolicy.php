<?php

namespace App\Policies;

use App\Models\{Invoice, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class InvoicePolicy
{
    use HandlesRoleAuthorization;

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
        return $user->canAccessFinance();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->canAccessFinance() && $invoice->status === 'draft';
    }
}
