<?php

namespace App\Domain\Core\Actions;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateProjectAction
{
    public function execute(array $data): Project
    {
        Gate::authorize('create', Project::class);

        return DB::transaction(function () use ($data) {
            $members = array_map('intval', $data['members'] ?? []);
            $roles = $data['roles'] ?? [];
            unset($data['members'], $data['roles']);
            $base = Str::slug($data['name']);
            $slug = $base;
            for ($suffix = 2; Project::where('slug', $slug)->exists(); $suffix++) {
                $slug = $base.'-'.$suffix;
            }
            $data['slug'] = $slug;
            $project = Project::create($data);
            if (! in_array(auth()->id(), $members, true)) {
                $members[] = auth()->id();
                $roles[auth()->id()] = 'sponsor';
            }
            $sync = [];
            foreach ($members as $id) {
                $sync[$id] = ['role' => $roles[$id] ?? 'member', 'assignment_status' => 'active', 'assigned_at' => now(), 'unassigned_at' => null];
            }
            $project->users()->sync($sync);

            return $project;
        });
    }
}
