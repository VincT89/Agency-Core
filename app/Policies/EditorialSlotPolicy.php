<?php

namespace App\Policies;

use App\Models\EditorialSlot;
use App\Models\User;

class EditorialSlotPolicy
{

    public function viewAny(User $user): bool
    {
        return $user->canManageSystem() || $user->isMarketing() || $user->isPhotographer();
    }


    public function view(User $user, EditorialSlot $editorialSlot): bool
    {
        if ($user->canManageSystem() || $user->isMarketing()) {
            return true;
        }

        // Applica vincolo di visibilità sul progetto
        return $user->projects()->where('projects.id', $editorialSlot->project_id)->exists();
    }


    public function cancel(User $user, EditorialSlot $editorialSlot): bool
    {
        return $user->canManageSystem() || $user->isMarketing();
    }


    public function publish(User $user, EditorialSlot $editorialSlot): bool
    {
        return $user->canManageSystem() || $user->isMarketing();
    }
}
