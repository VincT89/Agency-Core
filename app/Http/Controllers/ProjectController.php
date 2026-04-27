<?php

namespace App\Http\Controllers;

use App\Http\Requests\{StoreProjectRequest, UpdateProjectRequest};
use App\Models\{Client, Project, User};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends Controller
{

    public function index(\Illuminate\Http\Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $query = Project::query()
            ->with('client')
            ->withCount(['tasks', 'tickets']);

        if ($search = $request->get('search')) {
            $searchStr = '%' . strtolower($search) . '%';
            $query->where(function ($q) use ($searchStr) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchStr])
                  ->orWhereHas('client', function ($cq) use ($searchStr) {
                      $cq->whereRaw('LOWER(name) LIKE ?', [$searchStr]);
                  });
            });
        }

        $projects = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        $project = Project::create($data);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Progetto creato correttamente.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load(['client', 'users', 'tasks' => fn($q) => $q->latest()->limit(10),
                        'tickets' => fn($q) => $q->latest()->limit(10), 'attachments.uploader']);

        $project->loadCount([
            'tasks as total_tasks_count',
            'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'done')
        ]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        $users   = User::orderBy('name')->get(['id', 'name', 'role']);

        return view('projects.edit', compact('project', 'users'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

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
}
