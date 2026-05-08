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


    // Calendar state
    public $currentMonth;
    public $currentYear;

    public function mount(MarketingCampaign $campaign)
    {
        $this->authorize('view', $campaign);
        $this->campaign = $campaign;
        
        $this->currentMonth = (int) date('n');
        $this->currentYear = (int) date('Y');
    }

    public function previousMonth()
    {
        $this->currentMonth--;
        if ($this->currentMonth < 1) {
            $this->currentMonth = 12;
            $this->currentYear--;
        }
    }

    public function nextMonth()
    {
        $this->currentMonth++;
        if ($this->currentMonth > 12) {
            $this->currentMonth = 1;
            $this->currentYear++;
        }
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
        // Ottimizzazione: tutti i post nella lista
        $allPosts = $this->campaign->posts()
            ->with('currentVersion')
            ->orderBy('scheduled_date', 'asc')
            ->orderBy('scheduled_time', 'asc')
            ->limit(10)
            ->get();
        
        $calendarPostsRaw = $this->campaign->posts()
            ->whereNotNull('scheduled_date')
            ->whereYear('scheduled_date', $this->currentYear)
            ->whereMonth('scheduled_date', $this->currentMonth)
            ->orderBy('scheduled_date', 'asc')
            ->orderBy('scheduled_time', 'asc')
            ->get();
        
        // Raggruppiamo i post per il calendario
        $calendarPosts = [];
        foreach ($calendarPostsRaw as $p) {
            $dateStr = $p->scheduled_date->format('Y-m-d');
            if (!isset($calendarPosts[$dateStr])) {
                $calendarPosts[$dateStr] = [];
            }
            $calendarPosts[$dateStr][] = $p;
        }

        // Generazione griglia calendario
        $firstDayOfMonth = \Carbon\Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
        $daysInMonth = $firstDayOfMonth->daysInMonth;
        $startDayOfWeek = $firstDayOfMonth->dayOfWeekIso; // 1 = Lun, 7 = Dom

        $calendarGrid = [];
        $dayCounter = 1;

        // Riempimento griglia 6 righe x 7 colonne (lun-dom)
        for ($row = 0; $row < 6; $row++) {
            for ($col = 1; $col <= 7; $col++) {
                if ($row === 0 && $col < $startDayOfWeek) {
                    // Giorni vuoti prima dell'inizio del mese
                    $calendarGrid[$row][$col] = null;
                } elseif ($dayCounter <= $daysInMonth) {
                    $dateStr = sprintf('%04d-%02d-%02d', $this->currentYear, $this->currentMonth, $dayCounter);
                    $calendarGrid[$row][$col] = [
                        'day' => $dayCounter,
                        'date' => $dateStr,
                        'isToday' => $dateStr === date('Y-m-d'),
                        'posts' => $calendarPosts[$dateStr] ?? []
                    ];
                    $dayCounter++;
                } else {
                    // Giorni vuoti dopo la fine del mese
                    $calendarGrid[$row][$col] = null;
                }
            }
            if ($dayCounter > $daysInMonth) break;
        }

        return view('livewire.social.marketing-campaigns.marketing-campaign-show', [
            'posts' => $allPosts, // passiamo tutti i post all'aside
            'calendarGrid' => $calendarGrid,
            'monthName' => $firstDayOfMonth->translatedFormat('F Y'),
            'totalPostsCount' => $this->campaign->posts()->count(), // Passiamo il conteggio totale corretto
        ])->layout('layouts.app');
    }

}
