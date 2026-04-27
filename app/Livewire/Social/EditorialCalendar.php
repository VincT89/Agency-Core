<?php

namespace App\Livewire\Social;

use App\Models\EditorialSlot;
use App\Models\Project;
use App\Enums\Social\SocialPlatform;
use Livewire\Component;

class EditorialCalendar extends Component
{
    public $projectFilter = '';
    public $platformFilter = '';

    public function fetchEvents()
    {
        $query = EditorialSlot::with(['post.project', 'post.currentVersion'])
            ->where('status', '!=', \App\Enums\Social\EditorialSlotStatus::Cancelled);

        // Filtri
        if ($this->projectFilter) {
            $query->where('project_id', $this->projectFilter);
        }

        if ($this->platformFilter) {
            $query->where('platform', $this->platformFilter);
        }

        // Project Supremacy
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
                'start' => $slot->scheduled_at->toIso8601String(),
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
        // Solo per riempire la select
        $projects = Project::when(!auth()->user()->canManageSystem() && !auth()->user()->isMarketing(), function ($q) {
            $q->whereHas('users', function ($q2) {
                $q2->where('user_id', auth()->id());
            });
        })->get();

        $platforms = SocialPlatform::cases();

        return view('livewire.social.editorial-calendar', [
            'projects' => $projects,
            'platforms' => $platforms,
        ])->layout('layouts.app', ['title' => 'Calendario Editoriale Social']);
    }
}
