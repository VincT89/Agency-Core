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

    //

    public function store(Request $request, Ticket $ticket): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = (int) $ticket->checklistItems()->max('sort_order') + 1;

        $item = $ticket->checklistItems()->create([
            'title' => $data['title'],
            'sort_order' => $nextOrder,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'html' => view('shared.checklist-item', [
                    'item' => $item, 
                    'type' => 'ticket',
                ])->render(),
                'total' => $ticket->checklistItems()->count(),
                'done' => $ticket->checklistItems()->where('is_completed', true)->count(),
            ]);
        }

        return back()->with('success', 'Checklist aggiunta.');
    }

    public function update(Request $request, TicketChecklistItem $item): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $item->ticket);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $item->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'item' => [
                    'id' => $item->id,
                ]
            ]);
        }

        return back()->with('success', 'Checklist aggiornata.');
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

        app(\App\Services\AuditLogService::class)->log(
            'updated',
            $item->ticket,
            null,
            null,
            $newState
                ? auth()->user()->name . ' ha completato checklist "' . $item->title . '"'
                : auth()->user()->name . ' ha riaperto checklist "' . $item->title . '"'
        );

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
                'item' => [
                    'id' => $item->id,
                    'is_completed' => $item->is_completed,
                    'completed_by_name' => $item->completedBy?->name,
                ]
            ]);
        }

        return back();
    }

    public function destroy(TicketChecklistItem $item): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $item->ticket);

        $ticket = $item->ticket;
        $item->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'total' => $ticket->checklistItems()->count(),
                'done' => $ticket->checklistItems()->where('is_completed', true)->count(),
            ]);
        }

        return back()->with('success', 'Voce rimossa.');
    }
}
