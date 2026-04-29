<?php

namespace App\Policies;

use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SocialPostPolicy
{
    use \App\Policies\Concerns\HandlesRoleAuthorization;


    public function viewAny(User $user): bool
    {
        return true; // Accesso globale alla dashboard dei post
    }


    public function view(User $user, SocialPost $socialPost): bool
    {
        if ($user->isMarketing()) {
            return true;
        }

        // Filtra la visibilità dei post al progetto di appartenenza
        return $user->projects()->where('projects.id', $socialPost->project_id)->exists();
    }


    public function create(User $user): bool
    {
        return $user->isMarketing();
    }


    public function update(User $user, SocialPost $socialPost): bool
    {
        return $user->isMarketing();
    }


    public function delete(User $user, SocialPost $socialPost): bool
    {
        return false;
    }


    public function restore(User $user, SocialPost $socialPost): bool
    {
        return false;
    }


    public function forceDelete(User $user, SocialPost $socialPost): bool
    {
        return false;
    }


    public function requestRegeneration(User $user, SocialPost $socialPost): bool
    {
        // Riservato al team Marketing/Admin
        return $user->isMarketing();
    }


    public function sendToClient(User $user, SocialPost $socialPost): bool
    {
        // Riservato al team Marketing/Admin
        return $user->isMarketing();
    }


    public function schedule(User $user, SocialPost $socialPost): bool
    {
        return $user->isMarketing();
    }
}
