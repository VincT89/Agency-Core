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
            return false; // Nega accesso se il modello padre non esiste
        }

        return $user->can('view', $attachment->attachable);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if (! $attachment->attachable) {
            return false; // Nega eliminazione se il modello padre non esiste
        }

        // Controlla diritti di visualizzazione sul padre se l'utente è l'autore
        if ($attachment->uploaded_by === $user->id) {
            return $user->can('view', $attachment->attachable);
        }

        // Bypass per dipartimento Finance su fatture e pagamenti
        if ($user->canAccessFinance() && in_array(get_class($attachment->attachable), [
            \App\Models\Invoice::class, 
            \App\Models\Payment::class
        ])) {
            return true;
        }

        return false; // Autorizzazione gestita dal metodo before()
    }
}
