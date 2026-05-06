<?php

namespace App\Livewire\Social;

use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaign;
use App\Models\Client;
use App\Enums\Social\MarketingCampaignPostStatus;
use Livewire\Component;

class MarketingCampaignCalendar extends Component
{
    public $clientFilter = '';
    public $campaignFilter = '';
    public $platformFilter = '';

    public function fetchEvents()
    {
        $query = MarketingCampaignPost::with(['campaign.client', 'currentVersion'])
            ->whereNotNull('scheduled_date')
            ->where('status', '!=', MarketingCampaignPostStatus::Cancelled);

        // Applica i filtri impostati dall'utente
        if ($this->clientFilter) {
            $query->whereHas('campaign', function($q) {
                $q->where('client_id', $this->clientFilter);
            });
        }

        if ($this->campaignFilter) {
            $query->where('marketing_campaign_id', $this->campaignFilter);
        }

        // TODO: Aggiungere filtro piattaforma se introdotto nel nuovo modulo

        // Applica le policy di sicurezza basate sui clienti visibili
        if (!auth()->user()->canManageSystem() && !auth()->user()->isMarketing()) {
            $query->whereHas('campaign.client', function($q) {
                $q->whereHas('users', function($q2) {
                    $q2->where('user_id', auth()->id());
                });
            });
        }

        $posts = $query->get();

        return $posts->map(function ($post) {
            $date = $post->scheduled_date->format('Y-m-d');
            $time = $post->scheduled_time ? date('H:i:s', strtotime($post->scheduled_time)) : '12:00:00';
            
            return [
                'id' => $post->id,
                'title' => $post->title ?? 'Post senza titolo',
                'start' => $date . 'T' . $time,
                'url' => route('marketing-campaigns.show', $post->marketing_campaign_id),
                'backgroundColor' => $post->status->color(),
                'borderColor' => $post->status->color(),
                'extendedProps' => [
                    'platform' => 'Social', // Placeholder finché non aggiungiamo la piattaforma
                    'campaign' => $post->campaign->name ?? '',
                    'status' => $post->status->label(),
                ]
            ];
        })->toArray();
    }

    public function render()
    {
        // Carica i dati per le select di filtraggio in base ai permessi
        $campaigns = MarketingCampaign::when(!auth()->user()->canManageSystem() && !auth()->user()->isMarketing(), function ($q) {
            $q->whereHas('client.users', function ($q2) {
                $q2->where('user_id', auth()->id());
            });
        })->get();

        $platforms = []; // Rimuoviamo SocialPlatform momentaneamente

        $clients = Client::query()->visibleTo(auth()->user())->orderBy('name')->get();

        return view('livewire.social.marketing-campaign-calendar', [
            'clients' => $clients,
            'campaigns' => $campaigns,
            'platforms' => $platforms,
        ])->layout('layouts.app', ['title' => 'Calendario Campagne Marketing']);
    }
}
