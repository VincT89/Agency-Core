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

        $events = $posts->map(function ($post) {
            $date = $post->scheduled_date->format('Y-m-d');
            $time = $post->scheduled_time ? date('H:i:s', strtotime($post->scheduled_time)) : '12:00:00';
            
            return [
                'id' => 'post_' . $post->id,
                'title' => $post->title ?? 'Post senza titolo',
                'start' => $date . 'T' . $time,
                'url' => route('marketing-campaigns.posts.show', [
                    'campaign' => $post->marketing_campaign_id,
                    'post' => $post->id
                ]),
                'backgroundColor' => $post->status->color(),
                'borderColor' => $post->status->color(),
                'extendedProps' => [
                    'type' => 'post',
                    'platform' => 'Social', // Placeholder finché non aggiungiamo la piattaforma
                    'campaign' => $post->campaign->name ?? '',
                    'client' => $post->campaign->client->name ?? '',
                    'status' => $post->status->label(),
                ]
            ];
        })->toArray();

        // Fetch Shoots
        $shootsQuery = \App\Models\Shooting\Shoot::query()
            ->whereNotNull('marketing_campaign_id')
            ->where('status', '!=', \App\Enums\Shooting\ShootStatus::Cancelled)
            ->with(['marketingCampaign.client', 'slots']);

        if ($this->clientFilter) {
            $shootsQuery->whereHas('marketingCampaign', function($q) {
                $q->where('client_id', $this->clientFilter);
            });
        }

        if ($this->campaignFilter) {
            $shootsQuery->where('marketing_campaign_id', $this->campaignFilter);
        }

        if (!auth()->user()->canManageSystem() && !auth()->user()->isMarketing()) {
            $shootsQuery->whereHas('marketingCampaign.client', function($q) {
                $q->whereHas('users', function($q2) {
                    $q2->where('user_id', auth()->id());
                });
            });
        }

        $shoots = $shootsQuery->get();

        foreach ($shoots as $shoot) {
            // Find a date from slots or calendar event
            $date = null;
            $time = '09:00:00';
            
            if ($shoot->selected_slot_id && $shoot->selectedSlot) {
                $date = $shoot->selectedSlot->date->format('Y-m-d');
                $time = $shoot->selectedSlot->starts_at ? $shoot->selectedSlot->starts_at->format('H:i:s') : '09:00:00';
            } elseif ($shoot->slots->isNotEmpty()) {
                $slot = $shoot->slots->first();
                $date = $slot->date->format('Y-m-d');
                $time = $slot->starts_at ? $slot->starts_at->format('H:i:s') : '09:00:00';
            } elseif ($shoot->calendarEvent) {
                $date = $shoot->calendarEvent->start_at->format('Y-m-d');
                $time = $shoot->calendarEvent->start_at->format('H:i:s');
            }

            if ($date) {
                $events[] = [
                    'id' => 'shoot_' . $shoot->id,
                    'title' => '📷 ' . ($shoot->title ?? 'Shooting'),
                    'start' => $date . 'T' . $time,
                    'url' => route('social.shooting.show', $shoot->id),
                    'backgroundColor' => '#ec4899', // Pink-500 for shooting
                    'borderColor' => '#db2777',
                    'extendedProps' => [
                        'type' => 'shoot',
                        'platform' => 'Shooting',
                        'campaign' => $shoot->marketingCampaign->name ?? '',
                        'client' => $shoot->marketingCampaign->client->name ?? '',
                        'status' => $shoot->status->label(),
                    ]
                ];
            }
        }

        return $events;
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
