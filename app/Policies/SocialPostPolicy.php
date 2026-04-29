<?php

namespace App\Policies;

use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SocialPostPolicy
{
    use \App\Policies\Concerns\HandlesRoleAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Tutti possono vedere la dashboard dei post
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SocialPost $socialPost): bool
    {
        if ($user->isMarketing()) {
            return true;
        }

        // Project Supremacy: Fotografri e altri possono vedere solo i post dei loro progetti
        return $user->projects()->where('projects.id', $socialPost->project_id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isMarketing();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SocialPost $socialPost): bool
    {
        return $user->isMarketing();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SocialPost $socialPost): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SocialPost $socialPost): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SocialPost $socialPost): bool
    {
        return false;
    }

    /**
     * Determine whether the user can request regeneration from n8n.
     */
    public function requestRegeneration(User $user, SocialPost $socialPost): bool
    {
        // I fotografi non possono chiedere rigenerazione, solo Social e Admin.
        return $user->isMarketing();
    }

    /**
     * Determine whether the user can send to client.
     */
    public function sendToClient(User $user, SocialPost $socialPost): bool
    {
        // I fotografi non possono inviare ai clienti
        return $user->isMarketing();
    }

    /**
     * Determine whether the user can schedule the post.
     */
    public function schedule(User $user, SocialPost $socialPost): bool
    {
        return $user->isMarketing();
    }
}
