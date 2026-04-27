<?php

namespace App\Livewire\Admin\Shooting;

use Livewire\Component;
use Livewire\WithPagination;
use App\Domain\Shooting\Queries\ShootQuery;
use App\Enums\Shooting\ShootStatus;

class ShootsIndex extends Component
{
    use WithPagination;

    public $status = '';
    public $search = '';

    protected $queryString = [
        'status' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function mount()
    {
        if (!auth()->user()->canManageSystem()) {
            abort(403);
        }
    }

    public function updating($name, $value)
    {
        if (in_array($name, ['status', 'search'])) {
            $this->resetPage();
        }
    }

    public function render(ShootQuery $query)
    {
        $filters = [
            'status' => $this->status,
            'search' => $this->search,
        ];

        $filters = array_filter($filters);

        $shoots = $query->forIndex($filters)->latest('created_at')->paginate(20);

        return view('livewire.admin.shooting.shoots-index', [
            'shoots' => $shoots,
            'statuses' => ShootStatus::cases(),
        ])->layout('layouts.app', ['title' => 'Gestione Shooting (Admin)']);
    }
}
