<?php

namespace App\Listeners;

use App\Domain\Availability\Actions\RecordPresence;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

class EndPresenceOnLogout
{
    public function handle(Logout $event): void
    {
        if ($event->guard === 'web' && $event->user instanceof User && request()->hasSession()) {
            app(RecordPresence::class)->logout($event->user, hash('sha256', request()->session()->getId()));
        }
    }
}
