<?php

namespace App\Http\Controllers;

use App\Http\Requests\{StoreProjectRequest, UpdateProjectRequest};
use App\Models\{Client, Project, User};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends Controller
{

    public function index(\Illuminate\Http\Request $request, \App\Domain\Core\Queries\ProjectQuery $projectQuery): View
    {
        $this->authorize('viewAny', Project::class);

        $projects = $projectQuery->forIndex($request->all())
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        $users = User::where('status', 'active')
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $departments = $users
            ->map(fn($u) => $u->role->label())
            ->filter()
            ->unique()
            ->values();

        return view('projects.create', compact('users', 'departments'));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);

        $members = $data['members'] ?? [];
        $roles = $data['roles'] ?? [];

        unset($data['members'], $data['roles']);

        $project = Project::create($data);

        if (! in_array(auth()->id(), $members, true)) {
            $members[] = auth()->id();
            $roles[auth()->id()] = 'sponsor';
        }

        $sync = [];

        foreach ($members as $userId) {
            $sync[$userId] = [
                'role' => $roles[$userId] ?? 'member',
                'assignment_status' => 'active',
                'assigned_at' => now(),
                'unassigned_at' => null,
            ];
        }

        $project->users()->sync($sync);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Progetto creato correttamente.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load([
            'client',
            'users',
            'tasks' => fn($q) => $q->latest()->limit(10),
            'tickets' => fn($q) => $q->latest()->limit(10),
            'attachments.uploader'
        ]);

        $project->loadCount([
            'tasks as total_tasks_count',
            'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'done')
        ]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        $users = User::where('status', 'active')
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $departments = $users
            ->map(fn($u) => $u->role->label())
            ->filter()
            ->unique()
            ->values();

        $project->load('users');

        return view('projects.edit', compact('project', 'users', 'departments'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();

        if (($data['name'] ?? null) && $data['name'] !== $project->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $project->id);
        }

        $members = $data['members'] ?? [];
        $roles = $data['roles'] ?? [];

        unset($data['members'], $data['roles']);

        $project->update($data);

        $sync = [];

        foreach ($members as $userId) {
            $existingPivot = $project->users()
                ->where('users.id', $userId)
                ->first()?->pivot;

            $sync[$userId] = [
                'role' => $roles[$userId] ?? 'member',
                'assignment_status' => 'active',
                'assigned_at' => $existingPivot?->assigned_at ?? now(),
                'unassigned_at' => null,
            ];
        }

        $project->users()->sync($sync);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Progetto aggiornato correttamente.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Progetto eliminato correttamente.');
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Project::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
