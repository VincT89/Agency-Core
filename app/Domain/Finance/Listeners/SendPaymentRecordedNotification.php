<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\Events\PaymentRecorded;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\PaymentRecordedNotification;
use Illuminate\Support\Facades\Notification;

class SendPaymentRecordedNotification
{
    public function handle(PaymentRecorded $event)
    {
        $payment = $event->payment;

        $notifiableUsers = User::whereIn('role', [UserRole::Admin, UserRole::Administration])
            ->where('status', 'active')
            ->get();

        Notification::send(
            $notifiableUsers,
            new PaymentRecordedNotification($payment)
        );
    }
}
