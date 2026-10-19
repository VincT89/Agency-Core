<?php

namespace App\Domain\Core\Actions;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

class ProtectAdministratorAccess
{
    public function execute(User $target, Closure $operation): mixed
    {
        return DB::transaction(function () use ($target, $operation) {
            /** Serialize account removals and demotions so concurrent requests cannot remove the last administrators. */
            User::administrators()->where('status', 'active')->orderBy('id')->lockForUpdate()->get(['id']);
            $target = User::query()->lockForUpdate()->findOrFail($target->getKey());

            return $operation($target);
        }, 3);
    }
}
