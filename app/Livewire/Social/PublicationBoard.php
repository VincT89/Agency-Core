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

    public function markAsPublished(int $postId, \App\Domain\Social\Actions\MarkSocialPostAsPublishedAction $action)
    {
        $post = SocialPost::findOrFail($postId);
        $this->authorize('update', $post);

        $action->execute($post);

        session()->flash('success', 'Post contrassegnato come pubblicato!');
    }

    public function render()
    {
        $posts = SocialPost::with(['marketingProject.client.socialAccounts', 'editorialPlanSlot', 'currentVersion', 'creator'])
            ->where('status', SocialPostStatus::ClientApproved->value)
            ->where('publication_status', \App\Enums\Social\PublicationStatus::Ready->value)
            ->where('publication_mode', PublicationMode::Manual->value)
            ->whereNull('published_at')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                      ->orWhereHas('marketingProject.client', function ($q2) {
                          $q2->where('name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->orderBy('created_at', 'asc')
            ->paginate(15);

        return view('livewire.social.publication-board', [
            'posts' => $posts,
        ])->layout('layouts.app', ['title' => 'Bacheca Pubblicazioni']);
    }
}
