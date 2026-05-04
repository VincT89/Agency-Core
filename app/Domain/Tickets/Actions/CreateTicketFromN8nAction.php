<?php

namespace App\Domain\Tickets\Actions;

use App\Models\Ticket;
use App\Models\MarketingProject;
use App\Models\User;
use Illuminate\Support\Str;

class CreateTicketFromN8nAction
{
    public function execute(array $data): Ticket
    {
        // Controllo idempotenza: se esiste già un ticket per questa esecuzione, lo aggiorniamo accodando il contesto
        if (!empty($data['n8n_execution_id'])) {
            $existing = Ticket::where('n8n_execution_id', $data['n8n_execution_id'])->first();
            if ($existing) {
                // Se c'è un nuovo errore sulla stessa execution, non lo perdiamo: lo accodiamo
                if (!empty($data['context'])) {
                    $oldContext = is_array($existing->context) ? $existing->context : [];
                    $mergedContext = array_merge($oldContext, [
                        'retry_at_' . now()->timestamp => $data['context']
                    ]);
                    
                    // Limit to last 10 retries to avoid infinite growth
                    if (count($mergedContext) > 10) {
                        $mergedContext = array_slice($mergedContext, -10, null, true);
                    }

                    $existing->update([
                        'context' => $mergedContext,
                    ]);
                }
                return $existing;
            }
        }

        // Recupero client_id in base al marketing project (se fornito)
        $clientId = null;
        $projectId = null;

        if (!empty($data['marketing_project_id'])) {
            $marketingProject = MarketingProject::with(['project' => fn($q) => $q->withoutGlobalScopes()])->find($data['marketing_project_id']);
            if ($marketingProject && $marketingProject->project) {
                $projectId = $marketingProject->project_id;
                $clientId = $marketingProject->project->client_id ?? null;
            }
        }

        // Determina chi crea il ticket (assegniamo al primo admin o a un utente di sistema se disponibile)
        $creator = User::where('role', \App\Enums\UserRole::Admin)->first();

        return Ticket::create([
            'client_id' => $clientId,
            'project_id' => $projectId,
            'created_by' => $creator?->id,
            'code' => 'N8N-' . strtoupper(Str::random(6)),
            'title' => $data['title'],
            'description' => $data['description'],
            'type' => 'bug', // default type since 'error' is not in Ticket::TYPES
            'status' => 'open',
            'priority' => $data['priority'] ?? 'medium',
            'n8n_execution_id' => $data['n8n_execution_id'],
            'marketing_project_id' => $data['marketing_project_id'] ?? null,
            'social_post_id' => $data['social_post_id'] ?? null,
            'source' => $data['source'] ?? 'n8n',
            'context' => $data['context'] ?? null,
            'opened_at' => now(),
        ]);
    }
}
