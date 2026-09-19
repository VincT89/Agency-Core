<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAdministration() || $user->isCommercial();
    }

    public function view(User $user, Quote $quote): bool
    {
        return ! $quote->trashed() && ($this->create($user) || ($user->isCommercial() && $quote->status !== 'draft'
            && (int) $quote->client->commercial_user_id === (int) $user->id));
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isAdministration();
    }

    public function update(User $user, Quote $quote): bool
    {
        return $this->create($user) && ! $quote->trashed() && $quote->status === 'draft';
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $this->create($user) && ! $quote->trashed();
    }

    public function addAttachment(User $user, Quote $quote): bool
    {
        return $this->create($user) && ! $quote->trashed();
    }

    public function present(User $user, Quote $quote): bool
    {
        return $this->update($user, $quote);
    }

    public function respond(User $user, Quote $quote): bool
    {
        return $this->create($user) && ! $quote->trashed() && $quote->status === 'presented' && ! $quote->nextQuote()->withTrashed()->exists();
    }

    public function revise(User $user, Quote $quote): bool
    {
        return $this->create($user) && ! $quote->trashed() && in_array($quote->status, ['presented', 'rejected'], true);
    }

    public function createProject(User $user, Quote $quote): bool
    {
        return $this->create($user) && ! $quote->trashed() && $quote->status === 'accepted' && $user->can('create', Project::class);
    }
}
