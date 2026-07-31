<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const ACTIONS = [
        'created',
        'updated',
        'deleted',
        'status_changed',
        'assigned',
        'unassigned',
        'uploaded_attachment',
        'deleted_attachment',
        'payment_registered',
        'registered_payment',
        'deleted_payment',
        'password_reset',
        'login',
        'logout',
    ];

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'description',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getDisplayActionTextAttribute(): string
    {
        $entity = $this->displayEntityLabel();
        $status = $this->displayStatusLabel($this->new_values['status'] ?? null);

        return match ($this->action) {
            'created' => "ha creato {$entity}.",
            'deleted' => "ha eliminato {$entity}.",
            'status_changed' => $status
                ? "ha impostato {$entity} come {$status}."
                : "ha aggiornato lo stato di {$entity}.",
            'updated' => "ha aggiornato {$entity}.",
            'assigned' => "ha assegnato {$entity}.",
            'unassigned' => "ha rimosso l'assegnazione di {$entity}.",
            'payment_registered', 'registered_payment' => "ha registrato un pagamento per {$entity}.",
            'deleted_payment' => "ha eliminato un pagamento da {$entity}.",
            'uploaded_attachment' => "ha caricato un allegato per {$entity}.",
            'deleted_attachment' => "ha eliminato un allegato da {$entity}.",
            'password_reset' => "ha reimpostato la password per {$entity}.",
            'login' => "ha effettuato l'accesso.",
            'logout' => "ha terminato la sessione.",
            default => "ha aggiornato {$entity}.",
        };
    }

    public function getDisplayActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Creazione',
            'deleted' => 'Eliminazione',
            'status_changed' => 'Cambio stato',
            'assigned', 'unassigned' => 'Assegnazione',
            'payment_registered', 'registered_payment', 'deleted_payment' => 'Pagamento',
            'uploaded_attachment', 'deleted_attachment' => 'Allegato',
            'password_reset' => 'Sicurezza',
            'login', 'logout' => 'Accesso',
            default => 'Aggiornamento',
        };
    }

    private function displayEntityLabel(): string
    {
        $model = $this->auditable;
        $type = $this->auditable_type;

        return match (true) {
            is_a($type, \App\Models\Shooting\Shoot::class, true) => $model?->title
                ? "lo shooting \"{$model->title}\""
                : 'lo shooting selezionato',
            is_a($type, \App\Models\Ticket::class, true) => $model?->code
                ? "il ticket {$model->code}"
                : 'il ticket selezionato',
            is_a($type, \App\Models\Invoice::class, true) => $model?->number
                ? "la fattura {$model->number}"
                : 'la fattura selezionata',
            is_a($type, \App\Models\Payment::class, true) => 'il pagamento selezionato',
            is_a($type, \App\Models\Project::class, true) => $model?->name
                ? "il progetto \"{$model->name}\""
                : 'il progetto selezionato',
            is_a($type, \App\Models\Client::class, true) => $model?->name
                ? "il cliente {$model->name}"
                : 'il cliente selezionato',
            is_a($type, \App\Models\Task::class, true) => $model?->title
                ? "il task \"{$model->title}\""
                : 'il task selezionato',
            is_a($type, \App\Models\CalendarEvent::class, true) => $model?->title
                ? "l'evento \"{$model->title}\""
                : "l'evento selezionato",
            is_a($type, \App\Models\User::class, true) => $model?->name
                ? "l'utente {$model->name}"
                : "l'utente selezionato",
            default => 'il record selezionato',
        };
    }

    private function displayStatusLabel(mixed $status): ?string
    {
        if (! is_string($status) || $status === '') {
            return null;
        }

        return match ($status) {
            'open' => 'Aperto',
            'in_progress' => 'In lavorazione',
            'waiting' => 'In attesa',
            'resolved' => 'Risolto',
            'closed' => 'Chiuso',
            'todo' => 'Da fare',
            'review' => 'In revisione',
            'done' => 'Completata',
            'draft' => 'Bozza',
            'issued' => 'Emessa',
            'partially_paid' => 'Parzialmente saldata',
            'paid' => 'Saldata',
            'overdue' => 'Scaduta',
            'cancelled' => 'Annullata',
            'active' => 'Attivo',
            'inactive' => 'Inattivo',
            'completed' => 'Completato',
            'paused' => 'In pausa',
            'on_hold' => 'In pausa',
            'blocked' => 'Bloccato',
            'waiting_photographer' => 'In attesa del fotografo',
            'photographer_rejected' => 'Rifiutato dal fotografo',
            'waiting_client' => 'In attesa del cliente',
            'client_rejected' => 'Rifiutato dal cliente',
            'client_confirmed' => 'Confermato dal cliente',
            'scheduled' => 'Pianificato',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
