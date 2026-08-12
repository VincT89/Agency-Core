<?php

namespace App\Domain\Social\Exceptions;

use App\Models\MarketingCampaignPost;
use RuntimeException;

class MarketingCampaignPostArchiveException extends RuntimeException
{
    public static function notAllowed(MarketingCampaignPost $post): self
    {
        return new self(
            "Il post {$post->id} non può essere archiviato: lo stato o le pubblicazioni collegate richiedono che resti operativo."
        );
    }

    public static function alreadyArchived(MarketingCampaignPost $post): self
    {
        return new self("Il post {$post->id} è archiviato e non può essere pubblicato o riprovato.");
    }
}
