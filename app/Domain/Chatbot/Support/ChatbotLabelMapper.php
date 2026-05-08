<?php

namespace App\Domain\Chatbot\Support;

use BackedEnum;

class ChatbotLabelMapper
{
    public static function value(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    public static function status(mixed $value): ?string
    {
        return match (self::value($value)) {
            'open' => 'Aperto',
            'opened' => 'Aperto',
            'new' => 'Nuovo',
            'todo' => 'Da fare',
            'pending' => 'In attesa',
            'in_progress' => 'In lavorazione',
            'processing' => 'In lavorazione',
            'review' => 'In revisione',
            'approved' => 'Approvato',
            'rejected' => 'Rifiutato',
            'completed' => 'Completato',
            'done' => 'Completato',
            'closed' => 'Chiuso',
            'cancelled' => 'Annullato',
            'draft' => 'Bozza',
            'generated' => 'Generato',
            'regenerating' => 'In rigenerazione',
            'submitted_to_n8n' => 'Inviato a N8n',
            'pending_n8n' => 'In attesa di N8n',
            'ready_for_client' => 'Pronto per il cliente',
            'changes_requested' => 'Modifiche richieste',
            'published' => 'Pubblicato',
            'waiting' => 'In attesa',
            'waiting_customer_feedback' => 'In attesa di feedback',
            'active' => 'Attivo',
            'inactive' => 'Inattivo',
            'on_hold' => 'In pausa',
            'paused' => 'In pausa',
            'change' => 'Modifica',
            'change_request' => 'Richiesta di modifica',
            'sent_to_client' => 'Inviato al cliente',
            'client_changes_requested' => 'Modifiche richieste dal cliente',
            'client_approved' => 'Approvato dal cliente',
            'resolved' => 'Risolto',
            default => self::fallback($value),
        };
    }

    public static function priority(mixed $value): ?string
    {
        return match (self::value($value)) {
            'low' => 'Bassa',
            'medium' => 'Media',
            'normal' => 'Normale',
            'high' => 'Alta',
            'urgent' => 'Urgente',
            'critical' => 'Critica',
            default => self::fallback($value),
        };
    }

    public static function ticketType(mixed $value): ?string
    {
        return match (self::value($value)) {
            'bug' => 'Bug',
            'task' => 'Attività',
            'feature' => 'Nuova funzionalità',
            'support' => 'Supporto',
            'request' => 'Richiesta',
            'change' => 'Richiesta di modifica',
            'change_request' => 'Richiesta di modifica',
            default => self::fallback($value),
        };
    }

    public static function contentType(mixed $value): ?string
    {
        return match (self::value($value)) {
            'image' => 'Immagine',
            'video' => 'Video',
            'carousel' => 'Carosello',
            'reel' => 'Reel',
            'story' => 'Storia',
            'post' => 'Post',
            'text' => 'Testo',
            default => self::fallback($value),
        };
    }

    public static function platform(mixed $value): ?string
    {
        return match (self::value($value)) {
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'x' => 'X',
            'twitter' => 'X',
            default => self::fallback($value),
        };
    }

    public static function platforms(?array $values): array
    {
        return collect($values ?? [])
            ->map(fn ($value) => self::platform($value))
            ->filter()
            ->values()
            ->all();
    }

    public static function fallback(mixed $value): ?string
    {
        $value = self::value($value);

        if ($value === null || $value === '') {
            return null;
        }

        return str($value)
            ->replace('_', ' ')
            ->replace('-', ' ')
            ->title()
            ->toString();
    }
}
