<?php

namespace App\Livewire\Social\Posts;

use App\Models\SocialPost;
use Livewire\Component;
use Livewire\WithPagination;

class SocialPostsIndex extends Component
{
    use WithPagination;

    public $statusFilter = '';

    public function mount()
    {
        \Illuminate\Support\Facades\Gate::authorize('viewAny', SocialPost::class);
    }

    public function render()
    {
        $query = SocialPost::query()
            ->visibleTo(auth()->user())
            ->with(['client', 'project', 'currentVersion']);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $posts = $query->orderBy('updated_at', 'desc')->paginate(15);

        return view('livewire.social.posts.social-posts-index', [
            'posts' => $posts,
        ])->layout('layouts.app', ['title' => 'Gestione Social Post']);
    }
}
