<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{

    public const STATUSES   = ['todo', 'in_progress', 'waiting', 'done'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function index(Request $request, \App\Domain\Core\Queries\TaskQuery $taskQuery): View
    {
        $this->authorize('viewAny', Task::class);

        $viewMode = $request->get('view') === 'kanban' ? 'kanban' : 'list';
        
        $taskList = null;
        $kanbanTasks = null;

        if ($viewMode === 'kanban') {
            $kanbanTasks = $taskQuery->forKanban($request->all())->get();
        } else {
            $taskList = $taskQuery->forIndex($request->all())->paginate(20)->withQueryString();
        }

        $projects = Project::orderBy('name')->get(['id', 'name']);
        $users    = User::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('tasks.index', compact('viewMode', 'taskList', 'kanbanTasks', 'projects', 'users'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);

        $projects = Project::with('client')->orderBy('name')->get();
        $users    = User::where('status', 'active')->orderBy('name')->get();

        // Pre-selezione progetto da query string
        $preselectedProjectId = $request->project_id;

        return view('tasks.create', [
            'projects'            => $projects,
            'users'               => $users,
            'statuses'            => self::STATUSES,
            'priorities'          => self::PRIORITIES,
            'preselectedProjectId'=> $preselectedProjectId,
        ]);
    }

    public function store(Request $request, \App\Domain\Core\Actions\CreateTaskAction $action): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $data = $request->validate([
            'project_id'  => ['required', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['required', 'in:' . implode(',', self::STATUSES)],
            'priority'    => ['required', 'in:' . implode(',', self::PRIORITIES)],
            'start_date'  => ['nullable', 'date'],
            'due_date'    => ['nullable', 'date'],
            'notes'       => ['nullable', 'string'],
        ]);

        $data['created_by'] = auth()->id();

        if ($data['status'] === 'done') {
            $data['completed_at'] = now();
        }

        $task = $action->execute($data);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task creato correttamente.');
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);
        $task->load(['project.client', 'creator', 'assignee', 'attachments', 'auditLogs.user']);

        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);

        $projects = Project::with('client')->orderBy('name')->get();
        $users    = User::where('status', 'active')->orderBy('name')->get();

        return view('tasks.edit', [
            'task'       => $task,
            'projects'   => $projects,
            'users'      => $users,
            'statuses'   => self::STATUSES,
            'priorities' => self::PRIORITIES,
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'project_id'  => ['required', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['required', 'in:' . implode(',', self::STATUSES)],
            'priority'    => ['required', 'in:' . implode(',', self::PRIORITIES)],
            'start_date'  => ['nullable', 'date'],
            'due_date'    => ['nullable', 'date'],
            'notes'       => ['nullable', 'string'],
        ]);

        // Gestione completed_at
        if ($data['status'] === 'done' && !$task->completed_at) {
            $data['completed_at'] = now();
        } elseif ($data['status'] !== 'done') {
            $data['completed_at'] = null;
        }

        $task->update($data);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task aggiornato correttamente.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $projectId = $task->project_id;
        $task->delete();

        return redirect()->route('tasks.index')
            ->with('success', 'Task eliminato correttamente.');
    }

    /**
     * Aggiornamento rapido status via AJAX (per kanban o checkbox)
     */
    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
        ]);

        if ($data['status'] === 'done' && !$task->completed_at) {
            $data['completed_at'] = now();
        } elseif ($data['status'] !== 'done') {
            $data['completed_at'] = null;
        }

        $task->update($data);

        return redirect()->back()->with('success', 'Stato task aggiornato!');
    }
}
