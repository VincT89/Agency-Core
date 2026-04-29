<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{


    public function index(): View
    {
        $this->authorize('viewAny', Team::class);
        $teams = Team::withCount('users')
            ->orderBy('name')
            ->paginate(20);

        return view('teams.index', compact('teams'));
    }

    public function create(): View
    {
        $this->authorize('create', Team::class);
        $users = User::where('status', 'active')->orderBy('name')->get();
        return view('teams.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Team::class);
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
            'members'     => ['nullable', 'array'],
            'members.*'   => ['exists:users,id'],
            'roles'       => ['nullable', 'array'],
        ]);

        $team = Team::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        // Associa i membri al team con il relativo ruolo
        if (!empty($data['members'])) {
            $sync = [];
            foreach ($data['members'] as $userId) {
                $sync[$userId] = [
                    'role'              => $data['roles'][$userId] ?? 'member',
                    'assignment_status' => 'active',
                    'joined_at'         => now(),
                ];
            }
            $team->users()->sync($sync);
        }

        return redirect()->route('teams.show', $team)
            ->with('success', 'Team creato correttamente.');
    }

    public function show(Team $team): View
    {
        $this->authorize('view', $team);
        $team->load(['users' => fn($q) => $q->orderBy('name')]);
        return view('teams.show', compact('team'));
    }

    public function edit(Team $team): View
    {
        $this->authorize('update', $team);
        $team->load('users');
        $users = User::where('status', 'active')->orderBy('name')->get();
        return view('teams.edit', compact('team', 'users'));
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
            'members'     => ['nullable', 'array'],
            'members.*'   => ['exists:users,id'],
            'roles'       => ['nullable', 'array'],
        ]);

        $team->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        $sync = [];
        foreach ($data['members'] ?? [] as $userId) {
            $sync[$userId] = [
                'role'              => $data['roles'][$userId] ?? 'member',
                'assignment_status' => 'active',
                'joined_at'         => $team->users()->where('user_id', $userId)->first()?->pivot->joined_at ?? now(),
            ];
        }
        $team->users()->sync($sync);

        return redirect()->route('teams.show', $team)
            ->with('success', 'Team aggiornato correttamente.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);
        $team->delete();
        return redirect()->route('teams.index')
            ->with('success', 'Team eliminato correttamente.');
    }
}
