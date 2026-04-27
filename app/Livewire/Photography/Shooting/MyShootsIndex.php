<?php

namespace App\Livewire\Photography\Shooting;

use Livewire\Component;
use Livewire\WithPagination;
use App\Domain\Shooting\Queries\ShootQuery;
use App\Enums\Shooting\ShootStatus;

class MyShootsIndex extends Component
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
        if (!auth()->user()->isPhotographer()) {
            abort(403, 'Accesso negato: sezione riservata ai fotografi.');
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

        // Il fotografo vede solo i propri shoot.
        // UserScopedShootScope lo fa già, ma per chiarezza lo limitiamo anche esplicitamente qui se necessario (opzionale).
        $shoots = $query->forIndex($filters)->latest('created_at')->paginate(20);

        return view('livewire.photography.shooting.my-shoots-index', [
            'shoots' => $shoots,
            'statuses' => [
                ShootStatus::WaitingPhotographer,
                ShootStatus::WaitingClient,
                ShootStatus::Scheduled,
                ShootStatus::ClientRejected,
            ],
        ])->layout('layouts.app', ['title' => 'I Miei Shooting']);
    }
}
