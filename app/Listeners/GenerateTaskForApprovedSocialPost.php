<?php

namespace App\Listeners;

use App\Events\SocialPostApprovedByClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GenerateTaskForApprovedSocialPost
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected \App\Domain\Social\Actions\CreateMarketingPublicationTaskAction $action
    ) {}

    /**
     * Handle the event.
     */
    public function handle(SocialPostApprovedByClient $event): void
    {
        $post = $event->post;
        
        // Se è parte di un piano editoriale, i task vengono generati dall'approvazione del piano
        if ($post->editorial_plan_id || $post->editorial_plan_slot_id) {
            return;
        }

        if ($post->marketingProject) {
            $assignedTo = $post->marketingProject->created_by;
            if (!$assignedTo) $assignedTo = \App\Models\User::where('status', 'active')->where('role', \App\Enums\UserRole::Marketing)->first()?->id;
            if (!$assignedTo) $assignedTo = \App\Models\User::where('status', 'active')->where('role', \App\Enums\UserRole::Admin)->first()?->id;
            if (!$assignedTo) \Illuminate\Support\Facades\Log::warning('Nessun utente assegnabile trovato per il task del progetto ' . $post->marketingProject->id);

            $this->action->execute($post->marketingProject, $assignedTo);
        } else {
            $assignedTo = $post->created_by;
            if (!$assignedTo) $assignedTo = \App\Models\User::where('status', 'active')->where('role', \App\Enums\UserRole::Marketing)->first()?->id;
            if (!$assignedTo) $assignedTo = \App\Models\User::where('status', 'active')->where('role', \App\Enums\UserRole::Admin)->first()?->id;
            if (!$assignedTo) \Illuminate\Support\Facades\Log::warning('Nessun utente assegnabile trovato per il task del post ' . $post->id);

            \App\Models\Task::create([
                'project_id' => $post->project_id,
                'social_post_id' => $post->id,
                'created_by' => $post->created_by ?? 1,
                'assigned_to' => $assignedTo,
                'title' => 'Pubblicare post: ' . $post->title,
                'description' => 'Il cliente ha approvato questo post. Procedere con la pubblicazione.',
                'status' => 'todo',
                'priority' => 'high',
            ]);
        }
    }
}
