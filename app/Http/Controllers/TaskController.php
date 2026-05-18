<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
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

        $projects = Project::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $users    = User::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('tasks.index', compact('viewMode', 'taskList', 'kanbanTasks', 'projects', 'users'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);

        $projects = Project::with('client')->where('status', 'active')->orderBy('name')->get();
        $users    = User::where('status', 'active')->orderBy('name')->get();

        // Precompila l'ID progetto se fornito via querystring
        $preselectedProjectId = $request->project_id;

        if (empty($preselectedProjectId)) {
            $preselectedProjectId = \App\Models\Project::where('slug', 'progetto-interno')
                ->where('status', 'active')
                ->value('id');
        }

        $sourceTicket = null;
        if ($request->filled('ticket_id')) {
            $sourceTicket = Ticket::with(['project', 'client'])->findOrFail($request->ticket_id);
        }

        return view('tasks.create', [
            'projects'            => $projects,
            'users'               => $users,
            'statuses'            => self::STATUSES,
            'priorities'          => self::PRIORITIES,
            'preselectedProjectId'=> $preselectedProjectId,
            'sourceTicket'        => $sourceTicket,
        ]);
    }

    public function store(Request $request, \App\Domain\Core\Actions\CreateTaskAction $action): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $data = $request->validate([
            'project_id'  => ['required', 'exists:projects,id'],
            'ticket_id'   => ['nullable', 'exists:tickets,id'],
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

        if ($task->ticket && $task->ticket->status === 'open') {
            $task->ticket->update(['status' => 'in_progress']);
        }

        return redirect()->route('tasks.index')
            ->with('success', 'Task creato correttamente.');
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);
        $task->load([
            'project.client', 
            'creator', 
            'assignee', 
            'attachments', 
            'auditLogs' => fn ($q) => $q->with('user')->latest()->limit(8),
            'comments.user',
            'checklistItems.completedBy',
        ]);

        $projectTasks = collect();

        if ($task->project_id) {
            $projectTasks = Task::query()
                ->where('project_id', $task->project_id)
                ->visibleTo(request()->user())
                ->with(['assignee'])
                ->orderByRaw("
                    CASE status
                        WHEN 'in_progress' THEN 1
                        WHEN 'review' THEN 2
                        WHEN 'waiting' THEN 3
                        WHEN 'todo' THEN 4
                        WHEN 'done' THEN 5
                        WHEN 'cancelled' THEN 6
                        ELSE 7
                    END
                ")
                ->orderBy('due_date', 'asc')
                ->latest('updated_at')
                ->limit(50)
                ->get();
        }

        return view('tasks.show', compact('task', 'projectTasks'));
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);

        $projects = Project::with('client')->where('status', 'active')->orderBy('name')->get();
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
            'ticket_id'   => ['nullable', 'exists:tickets,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['required', 'in:' . implode(',', self::STATUSES)],
            'priority'    => ['required', 'in:' . implode(',', self::PRIORITIES)],
            'start_date'  => ['nullable', 'date'],
            'due_date'    => ['nullable', 'date'],
            'notes'       => ['nullable', 'string'],
        ]);

        // Imposta la data di completamento se il task viene chiuso
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
