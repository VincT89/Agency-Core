<?php

namespace App\Policies;

use App\Models\{Attachment, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class AttachmentPolicy
{
    use HandlesRoleAuthorization;

    public function download(User $user, Attachment $attachment): bool
    {
        if (! $attachment->attachable) {
            return false; // Deny if parent model corrupted
        }

        return $user->can('view', $attachment->attachable);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if (! $attachment->attachable) {
            return false; // Deny if parent model corrupted
        }

        // Must still have view/update rights to the parent if not the uploader
        if ($attachment->uploaded_by === $user->id) {
            return $user->can('view', $attachment->attachable);
        }

        // Eccezione Finance: Administration può gestire gli allegati delle fatture/pagamenti
        if ($user->canAccessFinance() && in_array(get_class($attachment->attachable), [
            \App\Models\Invoice::class, 
            \App\Models\Payment::class
        ])) {
            return true;
        }

        return false; // Handled by before()
    }
}
