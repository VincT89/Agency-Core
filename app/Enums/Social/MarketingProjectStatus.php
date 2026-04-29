<?php

namespace App\Enums\Social;

enum MarketingProjectStatus: string
{
    case Draft = 'draft';
    case QueuedToN8n = 'queued_to_n8n';
    case N8nFailed = 'n8n_failed';
    case SubmittedToN8n = 'submitted_to_n8n';
    case PostsReceived = 'posts_received';
    case InternalReview = 'internal_review';
    case SentToClient = 'sent_to_client';
    case ClientChangesRequested = 'client_changes_requested';
    case ClientApproved = 'client_approved';
    case ReadyToPublish = 'ready_to_publish';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Bozza',
            self::QueuedToN8n => 'In coda',
            self::N8nFailed => 'Errore Invio',
            self::SubmittedToN8n => 'Inviato a n8n',
            self::PostsReceived => 'Post Ricevuti',
            self::InternalReview => 'Revisione Interna',
            self::SentToClient => 'Inviato al Cliente',
            self::ClientChangesRequested => 'Modifiche Richieste',
            self::ClientApproved => 'Approvato',
            self::ReadyToPublish => 'Pronto per Pubblicazione',
            self::Completed => 'Completato',
            self::Cancelled => 'Annullato',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft => 'var(--text3)',
            self::QueuedToN8n => 'var(--blue)',
            self::N8nFailed => 'var(--red)',
            self::SubmittedToN8n, self::PostsReceived => 'var(--purple)',
            self::InternalReview => 'var(--blue)',
            self::SentToClient => 'var(--teal)',
            self::ClientChangesRequested => 'var(--orange)',
            self::ClientApproved, self::ReadyToPublish, self::Completed => 'var(--green)',
            self::Cancelled => 'var(--red)',
        };
    }
}
