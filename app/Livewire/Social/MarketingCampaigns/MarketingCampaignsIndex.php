<?php

namespace App\Livewire\Social\MarketingCampaigns;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\MarketingCampaign;
use App\Models\Client;
use App\Enums\Social\MarketingCampaignStatus;
use Illuminate\Database\Eloquent\Builder;

class MarketingCampaignsIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $clientId = '';
    public $status = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'clientId' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingClientId()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        // Admin/System vedono tutti i clienti, gli altri solo i propri
        $clientsQuery = Client::query()->where('status', 'active');
        if (!$user->canManageSystem() && !$user->isMarketing()) {
            $clientsQuery->whereHas('projects.users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        $clients = $clientsQuery->orderBy('name')->get();

        $campaigns = MarketingCampaign::with(['client', 'creator'])
            ->when($this->search, function (Builder $query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhereHas('client', function (Builder $q) {
                          $q->where('name', 'like', '%' . $this->search . '%');
                      });
            })
            ->when($this->clientId, function (Builder $query) {
                $query->where('client_id', $this->clientId);
            })
            ->when($this->status, function (Builder $query) {
                $query->where('status', $this->status);
            })
            ->when(!$user->canManageSystem() && !$user->isMarketing(), function (Builder $query) use ($user) {
                // Filtro sicurezza
                $query->whereHas('client.projects.users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.social.marketing-campaigns.marketing-campaigns-index', [
            'campaigns' => $campaigns,
            'clients' => $clients,
            'statuses' => MarketingCampaignStatus::cases(),
        ])->layout('layouts.app');
    }
}
