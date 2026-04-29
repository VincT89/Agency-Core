<?php

namespace App\Enums\Social;

enum EditorialPlanSlotStatus: string
{
    case Empty = 'empty';
    case QueuedToN8n = 'queued_to_n8n';
    case SubmittedToN8n = 'submitted_to_n8n';
    case PostReceived = 'post_received';
    case InternalReview = 'internal_review';
    case SentToClient = 'sent_to_client';
    case ClientChangesRequested = 'client_changes_requested';
    case ClientApproved = 'client_approved';
    case TaskCreated = 'task_created';
    case Published = 'published';

    public function label(): string
    {
        return match($this) {
            self::Empty => 'Vuoto',
            self::QueuedToN8n => 'In coda',
            self::SubmittedToN8n => 'Inviato a n8n',
            self::PostReceived => 'Post Ricevuto',
            self::InternalReview => 'Revisione Interna',
            self::SentToClient => 'Inviato al Cliente',
            self::ClientChangesRequested => 'Modifiche Richieste',
            self::ClientApproved => 'Approvato',
            self::TaskCreated => 'Task Creato',
            self::Published => 'Pubblicato',
        };
    }
}
