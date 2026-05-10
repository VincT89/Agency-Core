<?php

namespace App\Livewire\Social\MarketingCampaigns;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\MarketingCampaign;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MarketingCampaignShow extends Component
{
    use WithFileUploads, AuthorizesRequests;

    public MarketingCampaign $campaign;
    public string $calendarDate;

    public function mount(MarketingCampaign $campaign)
    {
        $this->authorize('view', $campaign);
        $this->campaign = $campaign;
        $this->calendarDate = request('date', now()->toDateString());
    }

    public function setCalendarDate(string $date): void
    {
        try {
            $this->calendarDate = \Carbon\Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $this->calendarDate = now()->toDateString();
        }
        $this->dispatch('marketing-campaign-detail-calendar-date-changed', date: $this->calendarDate);
    }

    public function goToPreviousCalendarMonth(): void
    {
        $this->calendarDate = \Carbon\Carbon::parse($this->calendarDate)->subMonth()->toDateString();
        $this->dispatch('marketing-campaign-detail-calendar-date-changed', date: $this->calendarDate);
    }

    public function goToNextCalendarMonth(): void
    {
        $this->calendarDate = \Carbon\Carbon::parse($this->calendarDate)->addMonth()->toDateString();
        $this->dispatch('marketing-campaign-detail-calendar-date-changed', date: $this->calendarDate);
    }

    // --- NUOVI STATI LIFECYCLE CAMPAGNA ---
    public bool $showCampaignModal = false;
    public array $campaignForm = [];

    public bool $showExtendModal = false;
    public array $extendForm = [];

    public bool $showRenewModal = false;
    public array $renewForm = [];

    public bool $showExtraModal = false;
    public array $extraForm = [];

    public bool $showInvoiceModal = false;
    public array $invoiceForm = [];
    public array $pendingPeriodsForInvoice = [];
    public array $pendingExtrasForInvoice = [];
    public array $customLines = [];
    // --------------------------------------


    public function fetchEvents()
    {
        $posts = $this->campaign->posts()
            ->with('currentVersion')
            ->whereNotNull('scheduled_date')
            ->where('status', '!=', \App\Enums\Social\MarketingCampaignPostStatus::Cancelled)
            ->get();

        return $posts->map(function ($post) {
            $date = $post->scheduled_date->format('Y-m-d');
            $startStr = $date;
            if ($post->scheduled_time) {
                $startStr .= 'T' . date('H:i:s', strtotime($post->scheduled_time));
            }
            return [
                'id' => $post->id,
                'title' => $post->title ?: 'Senza Titolo',
                'start' => $startStr,
                'allDay' => empty($post->scheduled_time),
                'url' => route('marketing-campaigns.posts.show', ['campaign' => $this->campaign->id, 'post' => $post->id]),
                'backgroundColor' => $post->status->color(),
                'borderColor' => $post->status->color(),
                'extendedProps' => [
                    'platform' => $post->content_type->label(),
                    'status' => $post->status->label(),
                ]
            ];
        })->toArray();
    }

    private function publishedDates()
    {
        return $this->campaign->posts()
            ->whereNotNull('scheduled_date')
            ->where('status', '!=', \App\Enums\Social\MarketingCampaignPostStatus::Cancelled)
            ->pluck('scheduled_date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();
    }

    // --- NUOVI METODI LIFECYCLE CAMPAGNA ---

    public function openCampaignModal()
    {
        $this->authorize('update', $this->campaign);
        $this->campaignForm = [
            'client_id' => $this->campaign->client_id,
            'name' => $this->campaign->name,
            'description' => $this->campaign->description,
            'status' => $this->campaign->status->value,
            'starts_at' => $this->campaign->starts_at ? $this->campaign->starts_at->format('Y-m-d') : null,
            'ends_at' => $this->campaign->ends_at ? $this->campaign->ends_at->format('Y-m-d') : null,
            'monthly_fee' => $this->campaign->monthly_fee,
            'notes' => $this->campaign->notes,
        ];
        $this->showCampaignModal = true;
    }

    public function closeCampaignModal()
    {
        $this->showCampaignModal = false;
    }

    public function saveCampaign(\App\Domain\Social\Actions\UpdateMarketingCampaignAction $action)
    {
        $this->authorize('update', $this->campaign);
        
        $this->validate([
            'campaignForm.name' => 'required|string|max:255',
            'campaignForm.monthly_fee' => 'nullable|numeric|min:0',
        ]);

        $action->execute($this->campaign, $this->campaignForm);
        $this->closeCampaignModal();
        $this->dispatch('campaign-updated');
    }

    public function openExtendModal()
    {
        $this->authorize('update', $this->campaign);
        
        // Suggeriamo come "from_date" il giorno successivo all'ends_at attuale, se c'è
        $suggestedFrom = $this->campaign->ends_at ? clone $this->campaign->ends_at : now();
        if ($this->campaign->ends_at) {
            $suggestedFrom->addDay();
        }

        $this->extendForm = [
            'from_date' => $suggestedFrom->format('Y-m-d'),
            'to_date' => null,
            'amount' => $this->campaign->monthly_fee,
            'description' => 'Prolungamento gestione',
        ];
        $this->showExtendModal = true;
    }

    public function closeExtendModal()
    {
        $this->showExtendModal = false;
    }

    public function extendCampaign(\App\Domain\Social\Actions\ExtendMarketingCampaignAction $action)
    {
        $this->authorize('update', $this->campaign);
        
        $this->validate([
            'extendForm.from_date' => 'required|date',
            'extendForm.to_date' => 'nullable|date|after_or_equal:extendForm.from_date',
            'extendForm.amount' => 'nullable|numeric|min:0',
            'extendForm.description' => 'required|string',
        ]);

        $action->execute($this->campaign, $this->extendForm);
        $this->closeExtendModal();
        $this->dispatch('campaign-extended');
    }

    public function openRenewModal()
    {
        $this->authorize('update', $this->campaign);
        
        $this->renewForm = [
            'starts_at' => now()->format('Y-m-d'),
            'from_date' => now()->format('Y-m-d'),
            'to_date' => null,
            'amount' => $this->campaign->monthly_fee,
            'description' => 'Rinnovo contratto',
        ];
        $this->showRenewModal = true;
    }

    public function closeRenewModal()
    {
        $this->showRenewModal = false;
    }

    public function renewCampaign(\App\Domain\Social\Actions\RenewMarketingCampaignAction $action)
    {
        $this->authorize('update', $this->campaign);

        $this->validate([
            'renewForm.starts_at' => 'nullable|date',
            'renewForm.from_date' => 'required|date',
            'renewForm.to_date' => 'nullable|date|after_or_equal:renewForm.from_date',
            'renewForm.amount' => 'nullable|numeric|min:0',
            'renewForm.description' => 'required|string',
        ]);

        $action->execute($this->campaign, $this->renewForm);
        $this->closeRenewModal();
        $this->dispatch('campaign-renewed');
    }

    public function openExtraModal()
    {
        $this->authorize('update', $this->campaign);
        $this->extraForm = [
            'description' => '',
            'amount' => null,
            'occurred_on' => now()->format('Y-m-d'),
        ];
        $this->showExtraModal = true;
    }

    public function closeExtraModal()
    {
        $this->showExtraModal = false;
    }

    public function addExtra(\App\Domain\Social\Actions\AddMarketingCampaignExtraAction $action)
    {
        $this->authorize('update', $this->campaign);

        $this->validate([
            'extraForm.description' => 'required|string',
            'extraForm.amount' => 'required|numeric|min:0',
            'extraForm.occurred_on' => 'nullable|date',
        ]);

        $action->execute($this->campaign, $this->extraForm);
        $this->closeExtraModal();
        $this->dispatch('campaign-extra-added');
    }

    public function deleteExtra(int $extraId, \App\Domain\Social\Actions\CancelMarketingCampaignExtraAction $action)
    {
        $this->authorize('update', $this->campaign);

        $extra = $this->campaign->extras()->findOrFail($extraId);

        try {
            $action->execute($extra);
            $this->dispatch('campaign-extra-deleted');
        } catch (\Exception $e) {
            $this->addError('extraForm', $e->getMessage());
        }
    }

    public function openInvoiceModal()
    {
        $this->authorize('update', $this->campaign);
        
        $pendingPeriods = $this->campaign->periods()->whereNull('invoice_id')->get();
        $pendingExtras = $this->campaign->extras()->whereNull('invoice_id')->where('status', \App\Enums\Social\MarketingCampaignExtraStatus::Pending)->get();
        
        $this->pendingPeriodsForInvoice = $pendingPeriods->map(fn($p) => ['id' => $p->id, 'description' => $p->description, 'amount' => $p->amount])->toArray();
        $this->pendingExtrasForInvoice = $pendingExtras->map(fn($e) => ['id' => $e->id, 'description' => $e->description, 'amount' => $e->amount])->toArray();

        $this->invoiceForm = [
            'number' => '',
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'tax_amount' => 0,
            'period_ids' => $pendingPeriods->pluck('id')->toArray(),
            'extra_ids' => $pendingExtras->pluck('id')->toArray(),
        ];
        
        $this->customLines = [
            ['description' => '', 'quantity' => 1, 'unit_price' => ''],
        ];

        $this->showInvoiceModal = true;
    }

    public function addCustomLine(): void
    {
        $this->customLines[] = ['description' => '', 'quantity' => 1, 'unit_price' => ''];
    }

    public function removeCustomLine(int $index): void
    {
        array_splice($this->customLines, $index, 1);
    }

    public function closeInvoiceModal()
    {
        $this->showInvoiceModal = false;
    }

    public function generateInvoice(\App\Domain\Social\Actions\GenerateMarketingCampaignInvoiceAction $action)
    {
        $this->authorize('update', $this->campaign);

        $this->validate([
            'invoiceForm.number' => 'required|string|unique:invoices,number',
            'invoiceForm.issue_date' => 'required|date',
            'invoiceForm.due_date' => 'nullable|date|after_or_equal:invoiceForm.issue_date',
            'invoiceForm.tax_amount' => 'required|numeric|min:0',
            'customLines.*.description' => 'nullable|string|max:255',
            'customLines.*.quantity'    => 'nullable|numeric|min:0.01',
            'customLines.*.unit_price'  => 'nullable|numeric|min:0',
        ]);

        $this->invoiceForm['custom_lines'] = array_values(
            array_filter($this->customLines, fn($l) => !empty($l['description']) && $l['unit_price'] !== '')
        );

        try {
            $action->execute($this->campaign, $this->invoiceForm);
            
            $this->campaign->refresh();
            $this->campaign->load(['periods', 'extras', 'invoices.items']);
            
            $this->closeInvoiceModal();
            $this->dispatch('campaign-invoice-generated');
        } catch (\Exception $e) {
            $this->addError('invoiceForm', $e->getMessage());
        }
    }
    // ---------------------------------------------

    public function render()
    {
        $currentDate = \Carbon\Carbon::parse($this->calendarDate);

        $allPosts = $this->campaign->posts()
            ->with('currentVersion')
            ->orderBy('scheduled_date', 'asc')
            ->orderBy('scheduled_time', 'asc')
            ->limit(10)
            ->get();
        
        $firstDayOfMonth = $currentDate->copy()->startOfMonth();
        $daysInMonth = $firstDayOfMonth->daysInMonth;
        $startDayOfWeek = $firstDayOfMonth->dayOfWeekIso;

        $days = [];
        for ($i = 1; $i < $startDayOfWeek; $i++) {
            $days[] = $firstDayOfMonth->copy()->subDays($startDayOfWeek - $i);
        }
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $days[] = $firstDayOfMonth->copy()->addDays($i - 1);
        }
        $remaining = count($days) % 7;
        if ($remaining > 0) {
            $padding = 7 - $remaining;
            $lastDay = end($days);
            for ($i = 1; $i <= $padding; $i++) {
                $days[] = $lastDay->copy()->addDays($i);
            }
        }

        $prevMonth = $firstDayOfMonth->copy()->subMonth()->toDateString();
        $nextMonth = $firstDayOfMonth->copy()->addMonth()->toDateString();

        return view('livewire.social.marketing-campaigns.marketing-campaign-show', [
            'posts' => $allPosts,
            'totalPostsCount' => $this->campaign->posts()->count(),
            'currentDate' => $currentDate,
            'days' => $days,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'monthName' => $firstDayOfMonth->translatedFormat('F Y'),
            'publishedDates' => $this->publishedDates(),
        ])->layout('layouts.app');
    }

}
