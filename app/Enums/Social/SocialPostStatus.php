<?php

namespace App\Enums\Social;

enum SocialPostStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case InternalReview = 'internal_review';
    case ChangesRequested = 'changes_requested';
    case Regenerating = 'regenerating';
    case ReadyForClient = 'ready_for_client';
    case SentToClient = 'sent_to_client';
    case ClientChangesRequested = 'client_changes_requested';
    case ClientApproved = 'client_approved';
    case ClientRejected = 'client_rejected';
    case ReadyToPublish = 'ready_to_publish';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Bozza',
            self::Received => 'Ricevuto',
            self::InternalReview => 'Revisione Interna',
            self::ChangesRequested => 'Modifiche Richieste (Int)',
            self::Regenerating => 'In Rigenerazione',
            self::ReadyForClient => 'Pronto per Cliente',
            self::SentToClient => 'Inviato al Cliente',
            self::ClientChangesRequested => 'Modifiche Richieste (Cli)',
            self::ClientApproved => 'Approvato',
            self::ClientRejected => 'Rifiutato',
            self::ReadyToPublish => 'Pronto per Pubblicazione',
            self::Scheduled => 'Programmato',
            self::Published => 'Pubblicato',
            self::Archived => 'Archiviato',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft, self::Archived => 'var(--text3)',
            self::Received, self::InternalReview => 'var(--blue)',
            self::ChangesRequested, self::ClientChangesRequested => 'var(--orange)',
            self::Regenerating => 'var(--purple)',
            self::ReadyForClient, self::SentToClient => 'var(--teal)',
            self::ClientApproved, self::ReadyToPublish, self::Published => 'var(--green)',
            self::ClientRejected => 'var(--red)',
            self::Scheduled => 'var(--accent)',
        };
    }
}
