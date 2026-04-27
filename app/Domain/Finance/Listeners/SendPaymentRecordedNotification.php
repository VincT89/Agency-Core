<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\Events\PaymentRecorded;

class SendPaymentRecordedNotification
{
    public function handle(PaymentRecorded $event)
    {
        $payment = $event->payment;
        
        $notifiableUsers = \App\Models\User::whereIn('role', [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Administration])
            ->where('status', 'active')
            ->get();

        \Illuminate\Support\Facades\Notification::send(
            $notifiableUsers, 
            new \App\Notifications\PaymentRecordedNotification($payment)
        );
    }
}
