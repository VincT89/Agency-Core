<?php

namespace App\Enums\Social;

enum EditorialPlanStatus: string
{
    case Draft = 'draft';
    case DatesSelected = 'dates_selected';
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

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Bozza',
            self::DatesSelected => 'Date Selezionate',
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
        };
    }
}
