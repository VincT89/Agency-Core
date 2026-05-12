<?php

namespace App\Enums\Social;

enum MarketingCampaignPostStatus: string
{
    case Draft = 'draft';
    case PendingN8n = 'pending_n8n';
    case SubmittedToN8n = 'submitted_to_n8n';
    case Generated = 'generated';
    case Regenerating = 'regenerating';
    case ReadyForClient = 'ready_for_client';
    case SentToClient = 'sent_to_client';
    case ClientChangesRequested = 'client_changes_requested';
    case ClientApproved = 'client_approved';
    case Approved = 'approved';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Bozza',
            self::PendingN8n => 'In Coda Sody',
            self::SubmittedToN8n => 'In Elaborazione Sody',
            self::Generated => 'Generato',
            self::Regenerating => 'In Rigenerazione',
            self::ReadyForClient => 'Pronto per Cliente',
            self::SentToClient => 'Inviato al Cliente',
            self::ClientChangesRequested => 'Modifiche Cliente',
            self::ClientApproved => 'Approvato dal Cliente',
            self::Approved => 'Approvato (Finale)',
            self::Published => 'Pubblicato',
            self::Cancelled => 'Annullato',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft => 'var(--text3)',
            self::PendingN8n, self::SubmittedToN8n, self::Regenerating => 'var(--orange)',
            self::Generated, self::ReadyForClient => 'var(--blue)',
            self::SentToClient => 'var(--purple)',
            self::ClientChangesRequested => 'var(--red)',
            self::ClientApproved, self::Approved => 'var(--teal)',
            self::Published => 'var(--green)',
            self::Cancelled => 'var(--red)',
        };
    }
}
