<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markAllAsRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'Tutte le notifiche sono state segnate come lette.');
    }

    public function markAsReadAndRedirect(string $id): RedirectResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);

        $notification->markAsRead();

        // Safe fallback in case URL is missing in old/weirder data, though our payload ensures it
        return redirect()->to($notification->data['url'] ?? url('/dashboard'));
    }

    public function destroy(string $id): RedirectResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->delete();

        return back()->with('success', 'Notifica eliminata.');
    }
}
