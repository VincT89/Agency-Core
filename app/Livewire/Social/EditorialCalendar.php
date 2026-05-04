<?php

namespace App\Livewire\Social;

use App\Models\EditorialSlot;
use App\Models\Project;
use App\Models\Client;
use App\Enums\Social\SocialPlatform;
use Livewire\Component;

class EditorialCalendar extends Component
{
    public $clientFilter = '';
    public $projectFilter = '';
    public $platformFilter = '';

    public function fetchEvents()
    {
        $query = EditorialSlot::with(['post.project', 'post.currentVersion'])
            ->where('status', '!=', \App\Enums\Social\EditorialSlotStatus::Cancelled);

        // Applica i filtri impostati dall'utente
        if ($this->clientFilter) {
            $query->whereHas('post.project', function($q) {
                $q->where('client_id', $this->clientFilter);
            });
        }

        if ($this->projectFilter) {
            $query->where('project_id', $this->projectFilter);
        }

        if ($this->platformFilter) {
            $query->where('platform', $this->platformFilter);
        }

        // Applica le policy di sicurezza basate sull'assegnazione dei progetti
        if (!auth()->user()->canManageSystem() && !auth()->user()->isMarketing()) {
            $query->whereHas('project', function($q) {
                $q->whereHas('users', function($q2) {
                    $q2->where('user_id', auth()->id());
                });
            });
        }

        $slots = $query->get();

        return $slots->map(function ($slot) {
            return [
                'id' => $slot->id,
                'title' => $slot->post->title ?? 'Post senza titolo',
                'start' => $slot->scheduled_at->format('Y-m-d\TH:i:s'),
                'url' => route('social.posts.show', $slot->post_id ?? $slot->social_post_id),
                'backgroundColor' => $slot->status->color(),
                'borderColor' => $slot->status->color(),
                'extendedProps' => [
                    'platform' => $slot->platform->label(),
                    'project' => $slot->post->project->name ?? '',
                    'status' => $slot->status->label(),
                ]
            ];
        })->toArray();
    }

    public function render()
    {
        // Carica i dati per le select di filtraggio in base ai permessi
        $projects = Project::when(!auth()->user()->canManageSystem() && !auth()->user()->isMarketing(), function ($q) {
            $q->whereHas('users', function ($q2) {
                $q2->where('user_id', auth()->id());
            });
        })->get();

        $platforms = SocialPlatform::cases();

        $clients = Client::query()->visibleTo(auth()->user())->orderBy('name')->get();

        return view('livewire.social.editorial-calendar', [
            'clients' => $clients,
            'projects' => $projects,
            'platforms' => $platforms,
        ])->layout('layouts.app', ['title' => 'Calendario Editoriale Social']);
    }
}
