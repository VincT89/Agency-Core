<?php

namespace App\Listeners;

use App\Events\EditorialPlanApprovedByClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GenerateTasksForApprovedEditorialPlan
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected \App\Domain\Social\Actions\CreateEditorialPlanPublicationTasksAction $action
    ) {}

    /**
     * Handle the event.
     */
    public function handle(EditorialPlanApprovedByClient $event): void
    {
        $assignedTo = $event->plan->marketingProject->created_by;
        
        if (!$assignedTo) {
            $assignedTo = \App\Models\User::where('status', 'active')->where('role', \App\Enums\UserRole::Marketing)->first()?->id;
        }
        if (!$assignedTo) {
            $assignedTo = \App\Models\User::where('status', 'active')->where('role', \App\Enums\UserRole::Admin)->first()?->id;
        }

        if (!$assignedTo) {
            \Illuminate\Support\Facades\Log::warning('Nessun utente assegnabile trovato per i task del piano editoriale ' . $event->plan->id);
        }
        
        $this->action->execute($event->plan, $assignedTo);
    }
}
