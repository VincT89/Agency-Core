<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class AuditLogService
{
    // Whitelist dei campi rilevanti da salvare nel payload JSON per le operazioni di update.
    protected array $trackedFields = [
        \App\Models\Ticket::class => ['status', 'priority', 'assigned_to'],
        \App\Models\Invoice::class => ['status', 'total', 'due_date', 'project_id'],
        \App\Models\Payment::class => ['amount', 'payment_date'],
        \App\Models\Task::class => ['status', 'priority', 'assigned_to', 'due_date'],
        \App\Models\CalendarEvent::class => ['title', 'start_at', 'end_at', 'assigned_to'],
        \App\Models\Client::class => ['name', 'status'],
        \App\Models\Project::class => ['name', 'status'],
    ];

    public function log(
        string $action,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?int $userId = null
    ): ?AuditLog {
        // Intercetta update e filtra solo i campi tracciati
        if ($action === 'updated' || $action === 'status_changed') {
            $modelClass = get_class($auditable);
            $tracked = $this->trackedFields[$modelClass] ?? [];
            
            if (!empty($tracked)) {
                if (is_array($oldValues)) {
                    $oldValues = array_intersect_key($oldValues, array_flip($tracked));
                }
                if (is_array($newValues)) {
                    $newValues = array_intersect_key($newValues, array_flip($tracked));
                }
                
                // Se dopo il filtro non ci sono differenze reali nei campi tracked, evitiamo log inutile
                if (empty($newValues)) {
                    return null;
                }
            }
        }

        // Svuotiamo i payload su create e delete per evitare Json enormi
        if ($action === 'created') {
            $oldValues = null;
            $newValues = null;
        }

        if ($action === 'deleted') {
            $oldValues = null;
            $newValues = null;
        }

        $userId = $userId ?? auth()->id();
        
        if (!$description) {
            $description = $this->generateMessage($action, $auditable, $newValues, $userId);
        }

        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    protected function generateMessage(string $action, Model $auditable, ?array $newValues, ?int $userId): string
    {
        $userName = $userId ? (User::find($userId)?->name ?? 'Sistema') : 'Sistema';
        $entityName = $this->getEntityLabel($auditable);
        
        if ($action === 'created') {
            return "{$userName} ha creato {$entityName}";
        }
        
        if ($action === 'deleted') {
            return "{$userName} ha eliminato {$entityName}";
        }
        
        if ($action === 'payment_registered' || $action === 'registered_payment') {
            return "{$userName} ha registrato un pagamento su {$entityName}";
        }

        if ($action === 'password_reset') {
            return "{$userName} ha reimpostato la password per {$entityName}";
        }

        // Attachment actions (richiede override dal chiamante per dire _quale_ allegato, ma questo è un fallback utile)
        if ($action === 'uploaded_attachment') {
            return "{$userName} ha caricato un allegato su {$entityName}";
        }
        if ($action === 'deleted_attachment') {
            return "{$userName} ha eliminato un allegato da {$entityName}";
        }

        if ($action === 'status_changed' || ($action === 'updated' && isset($newValues['status']))) {
            $statusRaw = $newValues['status'] ?? $auditable->status;
            $statusName = $this->translateStatus($statusRaw);
            return "{$userName} ha aggiornato lo stato de {$entityName} a '{$statusName}'";
        }
        
        if ($action === 'updated' && isset($newValues['assigned_to'])) {
            $assigneeName = $newValues['assigned_to'] ? (User::find($newValues['assigned_to'])?->name ?? 'ignoto') : 'nessuno';
            return "{$userName} ha assegnato {$entityName} a {$assigneeName}";
        }

        return "{$userName} ha aggiornato {$entityName}";
    }

    protected function getEntityLabel(Model $model): string
    {
        if ($model instanceof \App\Models\Ticket) return "il ticket {$model->code}";
        if ($model instanceof \App\Models\Invoice) return "la fattura {$model->number}";
        if ($model instanceof \App\Models\Project) return "il progetto {$model->name}";
        if ($model instanceof \App\Models\Client) return "il cliente {$model->name}";
        if ($model instanceof \App\Models\Task) return "il task \"{$model->title}\"";
        if ($model instanceof \App\Models\CalendarEvent) return "l'evento \"{$model->title}\"";
        if ($model instanceof \App\Models\User) return "l'utente {$model->name}";
        return "l'entità";
    }

    protected function translateStatus(?string $status): string
    {
        $map = [
            'open' => 'Aperto',
            'in_progress' => 'In lavorazione',
            'waiting' => 'In attesa',
            'resolved' => 'Risolto',
            'closed' => 'Chiuso',
            'draft' => 'Bozza',
            'issued' => 'Emessa',
            'partially_paid' => 'Parzialmente saldata',
            'paid' => 'Saldata',
            'cancelled' => 'Annullata',
            'active' => 'Attivo',
            'completed' => 'Completato',
            'paused' => 'In pausa',
            'blocked' => 'Bloccato',
        ];

        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status ?? ''));
    }
}