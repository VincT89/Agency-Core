<?php

namespace App\Livewire\Social;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\PublicationMode;

class PublicationBoard extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function markAsPublished(int $postId)
    {
        $post = SocialPost::findOrFail($postId);
        $this->authorize('update', $post);

        $post->update([
            'status' => SocialPostStatus::Published,
            'publication_status' => \App\Enums\Social\PublicationStatus::Published,
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);

        if ($post->marketingProject) {
            // Se tutti i post del progetto sono pubblicati, potremmo chiudere il progetto
            $allPublished = $post->marketingProject->socialPosts()
                ->where('id', '!=', $post->id)
                ->where('status', '!=', SocialPostStatus::Published->value)
                ->doesntExist();

            if ($allPublished) {
                $post->marketingProject->update(['status' => \App\Enums\Social\MarketingProjectStatus::Completed->value]);
            }
        }

        if ($post->editorialPlanSlot) {
            $post->editorialPlanSlot->update(['status' => \App\Enums\Social\EditorialPlanSlotStatus::Published->value]);
        }

        // Trova il task associato e completalo
        $task = \App\Models\Task::where('social_post_id', $post->id)->where('status', '!=', 'done')->first();
        if ($task) {
            $task->update(['status' => 'done', 'completed_at' => now()]);
        }

        session()->flash('success', 'Post contrassegnato come pubblicato!');
    }

    public function render()
    {
        $posts = SocialPost::with(['marketingProject.client', 'editorialPlanSlot', 'currentVersion', 'creator'])
            ->where('status', SocialPostStatus::ClientApproved)
            ->where('publication_status', '!=', \App\Enums\Social\PublicationStatus::Published)
            ->where('publication_mode', PublicationMode::Manual)
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                      ->orWhereHas('marketingProject.client', function ($q) {
                          $q->where('name', 'like', '%' . $this->search . '%');
                      });
            })
            ->orderBy('created_at', 'asc')
            ->paginate(15);

        return view('livewire.social.publication-board', [
            'posts' => $posts,
        ])->layout('layouts.app', ['title' => 'Bacheca Pubblicazioni']);
    }
}
