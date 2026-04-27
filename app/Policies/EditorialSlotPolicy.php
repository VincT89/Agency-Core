<?php

namespace App\Policies;

use App\Models\EditorialSlot;
use App\Models\User;

class EditorialSlotPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canManageSystem() || $user->isMarketing() || $user->isPhotographer();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EditorialSlot $editorialSlot): bool
    {
        if ($user->canManageSystem() || $user->isMarketing()) {
            return true;
        }

        // Project Supremacy
        return $editorialSlot->project->users->contains($user);
    }

    /**
     * Determine whether the user can cancel the model.
     */
    public function cancel(User $user, EditorialSlot $editorialSlot): bool
    {
        return $user->canManageSystem() || $user->isMarketing();
    }

    /**
     * Determine whether the user can publish the model.
     */
    public function publish(User $user, EditorialSlot $editorialSlot): bool
    {
        return $user->canManageSystem() || $user->isMarketing();
    }
}
