<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskChecklistItemController extends Controller
{
    use AuthorizesRequests;

    //

    public function store(Request $request, Task $task): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = (int) $task->checklistItems()->max('sort_order') + 1;

        $item = $task->checklistItems()->create([
            'title' => $data['title'],
            'sort_order' => $nextOrder,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'html' => view('shared.checklist-item', [
                    'item' => $item, 
                    'type' => 'task',
                ])->render(),
                'total' => $task->checklistItems()->count(),
                'done' => $task->checklistItems()->where('is_completed', true)->count(),
            ]);
        }

        return back()->with('success', 'Checklist aggiunta.');
    }

    public function update(Request $request, TaskChecklistItem $item): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $item->task);

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

    public function toggle(Request $request, TaskChecklistItem $item): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $item->task);

        $newState = ! $item->is_completed;

        $item->update([
            'is_completed' => $newState,
            'completed_at' => $newState ? now() : null,
            'completed_by' => $newState ? auth()->id() : null,
        ]);

        app(\App\Services\AuditLogService::class)->log(
            'updated',
            $item->task,
            null,
            null,
            $newState
                ? auth()->user()->name . ' ha completato checklist "' . $item->title . '"'
                : auth()->user()->name . ' ha riaperto checklist "' . $item->title . '"'
        );

        $item->load('completedBy');

        if ($request->expectsJson()) {
            $total = $item->task->checklistItems()->count();
            $done = $item->task->checklistItems()->where('is_completed', true)->count();

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

    public function destroy(TaskChecklistItem $item): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $item->task);

        $task = $item->task;
        $item->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'total' => $task->checklistItems()->count(),
                'done' => $task->checklistItems()->where('is_completed', true)->count(),
            ]);
        }

        return back()->with('success', 'Voce rimossa.');
    }
}
