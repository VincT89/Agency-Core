<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketChecklistItemController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = (int) $ticket->checklistItems()->max('sort_order') + 1;

        $ticket->checklistItems()->create([
            'title' => $data['title'],
            'sort_order' => $nextOrder,
        ]);

        return back()->with('success', 'Checklist aggiunta.');
    }

    public function update(Request $request, TicketChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $item->ticket);

        return back();
    }

    public function toggle(Request $request, TicketChecklistItem $item): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $item->ticket);

        $newState = ! $item->is_completed;

        $item->update([
            'is_completed' => $newState,
            'completed_at' => $newState ? now() : null,
            'completed_by' => $newState ? auth()->id() : null,
        ]);

        $item->load('completedBy');

        if ($request->expectsJson()) {
            $total = $item->ticket->checklistItems()->count();
            $done = $item->ticket->checklistItems()->where('is_completed', true)->count();

            return response()->json([
                'ok' => true,
                'item_id' => $item->id,
                'is_completed' => $item->is_completed,
                'completed_by' => $item->completedBy?->name,
                'done' => $done,
                'total' => $total,
            ]);
        }

        return back();
    }

    public function destroy(TicketChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $item->ticket);

        $item->delete();

        return back()->with('success', 'Voce rimossa.');
    }
}
