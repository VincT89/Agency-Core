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

    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = (int) $task->checklistItems()->max('sort_order') + 1;

        $task->checklistItems()->create([
            'title' => $data['title'],
            'sort_order' => $nextOrder,
        ]);

        return back()->with('success', 'Checklist aggiunta.');
    }

    public function update(Request $request, TaskChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $item->task);

        // Per implementazioni future
        return back();
    }

    public function toggle(TaskChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $item->task);

        $newState = ! $item->is_completed;

        $item->update([
            'is_completed' => $newState,
            'completed_at' => $newState ? now() : null,
            'completed_by' => $newState ? auth()->id() : null,
        ]);

        return back();
    }

    public function destroy(TaskChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $item->task);

        $item->delete();

        return back()->with('success', 'Voce rimossa.');
    }
}
