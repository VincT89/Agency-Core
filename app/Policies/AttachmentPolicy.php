<?php

namespace App\Policies;

use App\Models\{Attachment, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class AttachmentPolicy
{
    use HandlesRoleAuthorization { before as private roleBefore; }

    public function before(User $user, string $ability, ?Attachment $attachment = null): ?bool
    {
        if ($attachment?->client_material_category !== null && $attachment?->attachable_type === (new \App\Models\Client)->getMorphClass()) {
            $client = \App\Models\Client::find($attachment->attachable_id);

            return $client && match ($ability) {
                'download' => $user->can('viewMaterials', $client),
                'delete' => $user->can('manageMaterials', $client),
                default => false,
            };
        }
        if ($attachment && $attachment->attachable_type === (new \App\Models\Quote)->getMorphClass()) {
            $quote = \App\Models\Quote::find($attachment->attachable_id);

            return $quote && match ($ability) {
                'download' => $user->can('view', $quote),
                'delete' => $user->can('addAttachment', $quote),
                default => false,
            };
        }

        return $this->roleBefore($user, $ability);
    }

    public function download(User $user, Attachment $attachment): bool
    {
        if ($user->isCommercial() && !$attachment->attachable instanceof \App\Models\Ticket) {
            return false;
        }
        if (! $attachment->attachable) {
            return false; // Nega accesso se il modello padre non esiste
        }

        return $user->can('view', $attachment->attachable);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->isCommercial() && !$attachment->attachable instanceof \App\Models\Ticket) {
            return false;
        }
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
