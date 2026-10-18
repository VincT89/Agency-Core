<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessFinance();
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $user->canAccessFinance();
    }

    public function create(User $user): bool
    {
        return $user->canAccessFinance();
    }

    public function allocate(User $user, CreditNote $creditNote): bool
    {
        return $user->canAccessFinance();
    }
}
