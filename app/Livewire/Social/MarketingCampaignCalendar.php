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
    public string $calendarDate;

    public function mount()
    {
        $this->calendarDate = request('date', now()->toDateString());
    }

    public function setCalendarDate(string $date): void
    {
        try {
            $this->calendarDate = \Carbon\Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $this->calendarDate = now()->toDateString();
        }
        $this->dispatch('marketing-global-calendar-date-changed', date: $this->calendarDate);
    }

    public function goToPreviousCalendarMonth(): void
    {
        $this->calendarDate = \Carbon\Carbon::parse($this->calendarDate)->subMonth()->toDateString();
        $this->dispatch('marketing-global-calendar-date-changed', date: $this->calendarDate);
    }

    public function goToNextCalendarMonth(): void
    {
        $this->calendarDate = \Carbon\Carbon::parse($this->calendarDate)->addMonth()->toDateString();
        $this->dispatch('marketing-global-calendar-date-changed', date: $this->calendarDate);
    }

    public function updatedClientFilter(): void
    {
        $this->dispatch('marketing-global-calendar-filters-updated');
    }

    public function updatedCampaignFilter(): void
    {
        $this->dispatch('marketing-global-calendar-filters-updated');
    }

    public function updatedPlatformFilter(): void
    {
        $this->dispatch('marketing-global-calendar-filters-updated');
    }

    private function baseQuery()
    {
        $query = MarketingCampaignPost::query()
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

        // Applica le policy di sicurezza basate sui clienti visibili
        if (!auth()->user()->canManageSystem() && !auth()->user()->isMarketing()) {
            $query->whereHas('campaign.client', function($q) {
                $q->whereHas('users', function($q2) {
                    $q2->where('user_id', auth()->id());
                });
            });
        }

        return $query;
    }

    public function fetchEvents()
    {
        $posts = $this->baseQuery()
            ->with(['campaign.client', 'currentVersion'])
            ->get();

        return $posts->map(function ($post) {
            $date = $post->scheduled_date->format('Y-m-d');
            $time = $post->scheduled_time ? date('H:i:s', strtotime($post->scheduled_time)) : '12:00:00';
            
            return [
                'id' => $post->id,
                'title' => $post->title ?? 'Post senza titolo',
                'start' => $date . 'T' . $time,
                'url' => route('marketing-campaigns.posts.show', [
                    'campaign' => $post->marketing_campaign_id,
                    'post' => $post->id
                ]),
                'backgroundColor' => $post->status->color(),
                'borderColor' => $post->status->color(),
                'extendedProps' => [
                    'platform' => 'Social', // Placeholder finché non aggiungiamo la piattaforma
                    'campaign' => $post->campaign->name ?? '',
                    'client' => $post->campaign->client->name ?? '',
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

        $publishedDates = $this->baseQuery()
            ->pluck('scheduled_date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();

        return view('livewire.social.marketing-campaign-calendar', [
            'clients' => $clients,
            'campaigns' => $campaigns,
            'platforms' => $platforms,
            'publishedDates' => $publishedDates,
        ])->layout('layouts.app', ['title' => 'Calendario Campagne Marketing']);
    }
}
