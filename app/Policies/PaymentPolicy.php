<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\{Payment, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class PaymentPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->canAccessFinance();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('view', $payment->invoice);
    }

    public function create(User $user): bool
    {
        return $user->canAccessFinance();
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->can('update', $payment->invoice);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->can('update', $payment->invoice);
    }
}
