<?php

namespace App\Livewire\Social\MarketingCampaigns;

use Livewire\Component;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Enums\Social\MarketingCampaignStatus;

class MarketingCampaignCreate extends Component
{
    public $client_id = '';
    public $name = '';
    public $description = '';
    public $starts_at = '';
    public $ends_at = '';
    public $monthly_fee = '';
    public $notes = '';

    protected function rules()
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'monthly_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function save()
    {
        $this->validate();

        $campaign = MarketingCampaign::create([
            'client_id' => $this->client_id,
            'created_by' => auth()->id(),
            'name' => $this->name,
            'description' => $this->description,
            'status' => MarketingCampaignStatus::Draft->value,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
            'monthly_fee' => $this->monthly_fee ?: null,
            'notes' => $this->notes,
        ]);

        return redirect()->route('marketing-campaigns.show', $campaign->id);
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

        return view('livewire.social.marketing-campaigns.marketing-campaign-create', [
            'clients' => $clients,
        ])->layout('layouts.app');
    }
}
