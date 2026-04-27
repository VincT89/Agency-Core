<?php

namespace App\Livewire\Social\Posts;

use App\Models\SocialPost;
use Livewire\Component;
use Livewire\WithPagination;

class SocialPostsIndex extends Component
{
    use WithPagination;

    public $statusFilter = '';

    public function render()
    {
        $query = SocialPost::with(['client', 'project', 'currentVersion']);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Limit visibility based on project if the user is a photographer.
        // Even if the policy handles viewAny, filtering here guarantees we only show authorized posts.
        if (auth()->user()->isPhotographer()) {
            $query->whereIn('project_id', auth()->user()->projects->pluck('id'));
        }

        $posts = $query->orderBy('updated_at', 'desc')->paginate(15);

        return view('livewire.social.posts.social-posts-index', [
            'posts' => $posts,
        ])->layout('layouts.app', ['title' => 'Gestione Social Post']);
    }
}
