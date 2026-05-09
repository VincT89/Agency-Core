<?php

namespace App\Policies;

use App\Models\{Expense, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class ExpensePolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->canAccessFinance();
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->canAccessFinance();
    }

    public function create(User $user): bool
    {
        return $user->canAccessFinance();
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->canAccessFinance();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->canAccessFinance();
    }
}
