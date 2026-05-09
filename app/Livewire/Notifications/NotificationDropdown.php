<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class NotificationDropdown extends Component
{
    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function deleteNotification($id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->delete();
        }
    }

    public function render()
    {
        $user = auth()->user();
        return view('livewire.notifications.notification-dropdown', [
            'unreadNotificationsCount' => $user->unreadNotifications()->count(),
            'latestNotifications' => $user->notifications()->latest()->take(10)->get(),
        ]);
    }
}
