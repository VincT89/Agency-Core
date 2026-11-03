<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class RecordPresenceLogin
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User
            || $event->user->status !== 'active' || ! request()->hasSession()) {
            return;
        }

        /** The authentication event also covers remembered logins; confirmation remains a separate action. */
        request()->session()->put('presence.login_at', now()->timestamp);
        request()->session()->put('presence.login_prompt', true);
        try {
            app(AuditLogService::class)->log('login', $event->user, userId: $event->user->id);
        } catch (\Exception $exception) {
            Log::error('Failed to record login.', ['user_id' => $event->user->id, 'exception' => $exception::class]);
        }
    }
}
