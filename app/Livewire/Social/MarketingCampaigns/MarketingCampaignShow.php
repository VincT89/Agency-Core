<?php

namespace App\Livewire\Social\MarketingCampaigns;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarketingCampaignShow extends Component
{
    use WithFileUploads, AuthorizesRequests;

    public MarketingCampaign $campaign;

    public $showPostModal = false;
    public ?MarketingCampaignPost $editingPost = null;
    public $newInternalComment = '';
    public ?string $generatedReviewLink = null;

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
    // --------------------------------------

    // Form state strutturato (Punto 1)
    public $form = [
        'title' => null,
        'description' => null,
        'content_type' => 'post',
        'scheduled_date' => null,
        'scheduled_time' => null,
        'status' => 'draft',
        'ai_analysis_enabled' => true,
        'media_source' => 'local',
        'nextcloud_path' => null,
    ];

    // Client Identity for N8N Runtime
    public $include_client_logo = true;
    public $include_client_header = true;
    public $runtime_logo;
    public $runtime_activity_description;
    public $save_runtime_logo_to_client = false;
    public $save_runtime_activity_to_client = false;

    public $media; // Uploaded file
    public $existing_media_url = null; // Preview of existing file
    
    // Nextcloud State
    public $nextcloud_browse_path = '/';
    public $nextcloud_files = [];
    public ?array $selected_nextcloud_file = null;

    protected function rules()
    {
        return [
            'form.title' => 'nullable|string|max:255',
            'form.description' => 'nullable|string',
            'form.content_type' => ['required', \Illuminate\Validation\Rule::in(['post', 'story', 'reel'])],
            'form.scheduled_date' => 'nullable|date',
            'form.scheduled_time' => 'nullable|date_format:H:i',
            'form.status' => ['required', \Illuminate\Validation\Rule::in(array_column(MarketingCampaignPostStatus::cases(), 'value'))],
            'form.ai_analysis_enabled' => 'boolean',
            'form.media_source' => ['required', \Illuminate\Validation\Rule::in(['local', 'nextcloud'])],
            'form.nextcloud_path' => 'nullable|string|max:255',
            'media' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'runtime_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'runtime_activity_description' => 'nullable|string|max:1000',
        ];
    }

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

    public function browseNextcloud(string $path = '/')
    {
        $service = new \App\Services\Integrations\Nextcloud\NextcloudService();
        if ($service->isConfigured()) {
            $this->nextcloud_browse_path = $path;
            $this->nextcloud_files = $service->listFiles($path);
        }
    }

    public function selectNextcloudFile($path, $name, $size, $mime = null)
    {
        $this->selected_nextcloud_file = [
            'path' => $path,
            'name' => $name,
            'size' => $size,
            'mime' => $mime,
        ];
        $this->form['nextcloud_path'] = $path;
    }

    public function removeNextcloudFile()
    {
        $this->selected_nextcloud_file = null;
        $this->form['nextcloud_path'] = null;
    }

    // Punto 1 e 4
    public function resetForm()
    {
        $this->resetValidation();
        $this->showPostModal = false;
        $this->editingPost = null;
        $this->generatedReviewLink = null;
        $this->form = [
            'title' => null,
            'description' => null,
            'content_type' => 'post',
            'scheduled_date' => null,
            'scheduled_time' => null,
            'status' => 'draft',
            'ai_analysis_enabled' => true,
            'media_source' => 'local',
            'nextcloud_path' => null,
        ];
        
        $this->resetRuntimeClientFields();

        $this->media = null;
        $this->existing_media_url = null;
        $this->selected_nextcloud_file = null;
        $this->nextcloud_browse_path = '/';
        $this->nextcloud_files = [];
    }

    public function resetRuntimeClientFields()
    {
        $this->include_client_logo = true;
        $this->include_client_header = true;
        $this->runtime_logo = null;
        $this->runtime_activity_description = null;
        $this->save_runtime_logo_to_client = false;
        $this->save_runtime_activity_to_client = false;
    }

    public function updatedFormAiAnalysisEnabled($value)
    {
        if (!$value) {
            $this->resetRuntimeClientFields();
        }
    }

    // Punto 4 e 8 e Calendario
    public function openPostModal(?int $postId = null, ?string $prefilledDate = null)
    {
        $this->resetForm();

        if ($postId) {
            $post = MarketingCampaignPost::findOrFail($postId);
            $this->authorize('update', $post);

            $this->editingPost = $post;
            $this->form = [
                'title' => $post->title,
                'description' => $post->description,
                'content_type' => $post->content_type->value,
                'scheduled_date' => $post->scheduled_date ? $post->scheduled_date->format('Y-m-d') : null,
                'scheduled_time' => $post->scheduled_time ? date('H:i', strtotime($post->scheduled_time)) : null,
                'status' => $post->status->value,
                'ai_analysis_enabled' => $post->ai_analysis_enabled,
                'media_source' => $post->media_source ?? 'local',
                'nextcloud_path' => $post->nextcloud_path,
            ];
            $this->existing_media_url = $post->media_url;
        } else {
            $this->authorize('update', $this->campaign);
            if ($prefilledDate) {
                $this->form['scheduled_date'] = $prefilledDate;
            }
        }

        $this->showPostModal = true;
    }

    // Punto 4
    public function closePostModal()
    {
        $this->showPostModal = false;
        $this->resetForm();
    }

    // Punto 2 e 8
    public function savePost()
    {
        $this->validate();

        if ($this->editingPost && $this->editingPost->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile modificare un post già pubblicato.');
            return;
        }

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;

        if (!$this->editingPost) {
            $data['created_by'] = auth()->id();
        }

        $oldMediaPath = null;
        if ($data['media_source'] === 'local' && $this->media) {
            $filename = Str::slug(pathinfo($this->media->getClientOriginalName(), PATHINFO_FILENAME)) 
                        . '_' . time() . '.' . $this->media->getClientOriginalExtension();
            $path = $this->media->storeAs('marketing/campaign-posts', $filename, 'public');

            $data['media_path'] = $path;
            $data['media_original_name'] = $this->media->getClientOriginalName();
            $data['media_mime'] = $this->media->getMimeType();

            if ($this->editingPost && $this->editingPost->media_path) {
                $oldMediaPath = 'marketing/campaign-posts/' . $this->editingPost->media_path;
            }
        } elseif ($data['media_source'] === 'nextcloud') {
            // Non elaboriamo $this->media, usiamo nextcloud_path (che è già in $data)
        }

        if ($this->editingPost) {
            $this->authorize('update', $this->editingPost);
            $this->editingPost->update($data);
        } else {
            $this->authorize('update', $this->campaign);
            MarketingCampaignPost::create($data);
        }

        if ($oldMediaPath) {
            Storage::disk('public')->delete($oldMediaPath);
        }

        $this->closePostModal();
        $this->dispatch('post-saved');
    }

    // Punto 5 e 6
    public function saveAndSubmitToN8n(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction $submitAction)
    {
        $this->validate();
        
        if ($this->editingPost && $this->editingPost->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile inviare a N8n un post già pubblicato.');
            return;
        }

        // Forziamo lo stato a pending_n8n
        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;
        $data['status'] = MarketingCampaignPostStatus::PendingN8n->value;

        if (!$this->editingPost) {
            $data['created_by'] = auth()->id();
        }

        $oldMediaPath = null;
        if ($data['media_source'] === 'local' && $this->media) {
            $filename = Str::slug(pathinfo($this->media->getClientOriginalName(), PATHINFO_FILENAME)) 
                        . '_' . time() . '.' . $this->media->getClientOriginalExtension();
            $path = $this->media->storeAs('marketing/campaign-posts', $filename, 'public');

            $data['media_path'] = $path;
            $data['media_original_name'] = $this->media->getClientOriginalName();
            $data['media_mime'] = $this->media->getMimeType();

            if ($this->editingPost && $this->editingPost->media_path) {
                $oldMediaPath = 'marketing/campaign-posts/' . $this->editingPost->media_path;
            }
        } elseif ($data['media_source'] === 'nextcloud') {
            // Niente
        }

        $post = $this->editingPost;
        if ($post) {
            $this->authorize('update', $post);
            $post->update($data);
        } else {
            $this->authorize('update', $this->campaign);
            $post = MarketingCampaignPost::create($data);
        }

        if ($oldMediaPath) {
            Storage::disk('public')->delete($oldMediaPath);
        }

        $runtimeClientData = [
            'include_client_logo' => $this->include_client_logo,
            'include_client_header' => $this->include_client_header,
            'runtime_logo' => $this->runtime_logo, // This is the UploadedFile or null
            'runtime_activity_description' => $this->runtime_activity_description,
            'save_runtime_logo_to_client' => $this->save_runtime_logo_to_client,
            'save_runtime_activity_to_client' => $this->save_runtime_activity_to_client,
        ];

        // Lancia Action N8n
        $submitAction->execute($post, $runtimeClientData);

        $this->closePostModal();
        $this->dispatch('post-submitted-n8n');
    }
    public function regeneratePost(int $postId, string $type, \App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction $action)
    {
        $post = MarketingCampaignPost::findOrFail($postId);
        $this->authorize('update', $post);

        if ($post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile rigenerare un post già pubblicato.');
            return;
        }

        try {
            $action->execute($post, auth()->user(), $type);
            $this->closePostModal();
            $this->dispatch('post-regenerating');
        } catch (\Exception $e) {
            $this->addError('post', $e->getMessage());
        }
    }

    public function sendToClient(int $postId, \App\Domain\Social\Actions\SendMarketingCampaignPostToClientAction $action)
    {
        $post = MarketingCampaignPost::findOrFail($postId);
        $this->authorize('update', $post);

        if ($post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile inviare in revisione un post già pubblicato.');
            return;
        }

        try {
            $token = $action->execute($post);
            $this->generatedReviewLink = route('public.marketing-campaign-posts.review', ['token' => $token->token]);
            $this->editingPost->refresh();
            $this->dispatch('post-sent-to-client');
        } catch (\Exception $e) {
            $this->addError('post', $e->getMessage());
        }
    }

    public function approvePost(int $postId)
    {
        $post = MarketingCampaignPost::findOrFail($postId);
        $this->authorize('update', $post);

        if ($post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Post già pubblicato.');
            return;
        }

        $post->update([
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Approved->value,
        ]);

        $this->closePostModal();
        $this->dispatch('post-approved');
    }

    public function addInternalComment(int $postId)
    {
        $this->validate(['newInternalComment' => 'required|string']);
        
        $post = MarketingCampaignPost::findOrFail($postId);
        $this->authorize('update', $post);

        $post->comments()->create([
            'marketing_campaign_post_version_id' => $post->current_version_id,
            'user_id' => auth()->id(),
            'body' => $this->newInternalComment,
            'visibility' => \App\Enums\Social\MarketingCampaignPostCommentVisibility::Internal->value,
            'type' => \App\Enums\Social\MarketingCampaignPostCommentType::Text->value,
        ]);

        $this->newInternalComment = '';
        $this->editingPost->refresh();
        $this->dispatch('internal-comment-added');
    }

    // Punto 8
    public function deletePost(int $postId)
    {
        $post = MarketingCampaignPost::findOrFail($postId);
        $this->authorize('delete', $post);

        if ($post->media_path) {
            Storage::disk('public')->delete('marketing/campaign-posts/' . $post->media_path);
        }

        $post->delete();
        
        $this->closePostModal();
        $this->dispatch('post-deleted');
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
        $this->showInvoiceModal = true;
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
        ]);

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
            ->orderBy('created_at', 'desc')
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
