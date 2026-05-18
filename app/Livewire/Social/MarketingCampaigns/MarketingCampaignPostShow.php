<?php

namespace App\Livewire\Social\MarketingCampaigns;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketingCampaignPostShow extends Component
{
    use WithFileUploads, AuthorizesRequests;

    public MarketingCampaign $campaign;
    public MarketingCampaignPost $post;

    // Form state strutturato
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
        'publishing_platforms' => [],
    ];

    public $newInternalComment = '';
    public ?string $generatedReviewLink = null;

    // Client Identity for N8N Runtime
    public $include_client_logo = true;
    public $include_client_header = true;
    public $runtime_logo;
    public $runtime_activity_description;
    public $save_runtime_logo_to_client = false;
    public $save_runtime_activity_to_client = false;

    public $media = []; // Uploaded file(s)
    public array $existing_media = []; // Existing media from DB

    // Nextcloud State
    public $nextcloud_media_kind = 'photo';
    public $nextcloud_browse_path = '/';
    public $nextcloud_files = [];
    public array $selected_nextcloud_files = [];
    public array $pending_nextcloud_files = [];
    public ?array $preview_nextcloud_file = null;
    public bool $showNextcloudPicker = false;
    public ?string $nextcloud_error = null;

    // Regeneration state
    public bool $regeneration_timeout = false;
    public int $regeneration_checks = 0;
    public bool $showCancelRegenerationButton = false;

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
            'form.publishing_platforms' => 'nullable|array',
            'form.publishing_platforms.*' => 'string|in:instagram,facebook,tiktok',
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:51200',
            ],
            'runtime_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'runtime_activity_description' => 'nullable|string|max:1000',
        ];
    }

    public function mount(MarketingCampaign $campaign, MarketingCampaignPost $post)
    {
        $this->authorize('view', $post);
        $this->campaign = $campaign;

        // Eager load related data
        $post->load(['currentVersion', 'comments.user']);
        $this->post = $post;
        $this->loadExistingMedia();

        $this->form = [
            'title' => $post->currentVersion ? ($post->currentVersion->title ?? $post->title) : $post->title,
            'description' => $post->currentVersion ? ($post->currentVersion->caption ?? $post->description) : $post->description,
            'content_type' => $post->content_type->value,
            'scheduled_date' => $post->scheduled_date ? $post->scheduled_date->format('Y-m-d') : null,
            'scheduled_time' => $post->scheduled_time ? date('H:i', strtotime($post->scheduled_time)) : null,
            'status' => $post->status->value,
            'ai_analysis_enabled' => $post->ai_analysis_enabled,
            'media_source' => $post->media_source ?? 'local',
            'nextcloud_path' => $post->nextcloud_path,
            'publishing_platforms' => $post->publishing_platforms ?? [],
        ];
    }

    #[On('post-saved')]
    #[On('post-submitted-n8n')]
    #[On('post-approved')]
    #[On('post-regenerating')]
    #[On('post-sent-to-client')]
    #[On('internal-comment-added')]
    public function refreshPost()
    {
        $this->post->refresh();
        $this->post->load(['currentVersion', 'comments.user']);
        $this->loadExistingMedia();
        $this->form['status'] = $this->post->status->value;

        if ($this->post->currentVersion) {
            $this->form['title'] = $this->post->currentVersion->title ?? $this->post->title;
            $this->form['description'] = $this->post->currentVersion->caption ?? $this->post->description;
        }
    }

    public function checkRegenerationStatus()
    {
        $this->post->refresh();
        $this->post->load(['currentVersion', 'comments.user']);
        $this->loadExistingMedia();
        
        $this->form['status'] = $this->post->status->value;

        if ($this->post->currentVersion) {
            $this->form['title'] = $this->post->currentVersion->title ?? $this->post->title;
            $this->form['description'] = $this->post->currentVersion->caption ?? $this->post->description;
        }

        if (! in_array($this->post->status->value, ['pending_n8n', 'submitted_to_n8n', 'regenerating'], true)) {
            $this->dispatch('marketing-post-regeneration-completed');
            $this->regeneration_timeout = false;
            $this->regeneration_checks = 0;
            return;
        }

        if (in_array($this->post->status->value, ['pending_n8n', 'submitted_to_n8n', 'regenerating'])) {
            $this->regeneration_checks++;
            if ($this->regeneration_checks >= 10) {
                $this->dispatch('show-sody-cancel-button');
                $this->regeneration_timeout = true;
            }
        } else {
            $this->regeneration_timeout = false;
            $this->regeneration_checks = 0;
        }
    }

    public function cancelRegeneration(): void
    {
        $previous = $this->post->n8n_previous_status?->value
            ?? \App\Enums\Social\MarketingCampaignPostStatus::Generated->value;

        $this->post->forceFill([
            'status' => $previous,
            'n8n_error' => 'N8N_ERROR_FORCE_CANCELLED',
            'n8n_completed_at' => null,
        ])->save();

        $this->post->refresh();

        $this->regeneration_timeout = false;
        $this->regeneration_checks = 0;

        $this->dispatch('marketing-post-regeneration-cancelled');
    }

    private function loadExistingMedia()
    {
        $this->existing_media = $this->post->mediaItems()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'source' => $item->source,
                    'path' => $item->source === 'local' ? $item->path : $item->nextcloud_path,
                    'preview_url' => $item->source === 'local' 
                        ? Storage::disk('public')->url($item->path)
                        : ($item->nextcloud_share_url ? $item->nextcloud_share_url . '/preview' : null),
                    'original_name' => $item->original_name,
                    'mime_type' => $item->mime_type,
                    'nextcloud_share_url' => $item->nextcloud_share_url,
                    'nextcloud_file_id' => $item->nextcloud_file_id,
                    'sort_order' => $item->sort_order,
                ];
            })->toArray();
    }

    private function syncLegacyMediaFields()
    {
        $first = collect($this->existing_media)->first();
        if ($first) {
            $this->post->update([
                'media_path' => $first['source'] === 'local' ? $first['path'] : null,
                'media_original_name' => $first['original_name'],
                'media_mime' => $first['mime_type'],
                'nextcloud_path' => $first['source'] === 'nextcloud' ? $first['path'] : null,
                'nextcloud_share_url' => $first['source'] === 'nextcloud' ? $first['nextcloud_share_url'] : null,
                'nextcloud_file_id' => $first['source'] === 'nextcloud' ? $first['nextcloud_file_id'] : null,
            ]);
            $this->form['media_source'] = $first['source'];
            $this->form['nextcloud_path'] = $first['source'] === 'nextcloud' ? $first['path'] : null;
        } else {
            $this->post->update([
                'media_path' => null,
                'media_original_name' => null,
                'media_mime' => null,
                'nextcloud_path' => null,
                'nextcloud_share_url' => null,
                'nextcloud_file_id' => null,
            ]);
            $this->form['nextcloud_path'] = null;
        }
        $this->post->refresh();
    }

    public function removeExistingMedia($id)
    {
        $this->authorize('update', $this->post);
        
        $item = \App\Models\MarketingCampaignPostMedia::find($id);
        if ($item && $item->marketing_campaign_post_id === $this->post->id) {
            if ($item->source === 'local' && $item->path) {
                Storage::disk('public')->delete($item->path);
            }
            $item->delete();
            $this->loadExistingMedia();
            $this->syncLegacyMediaFields();
        }
    }

    public function removeLocalMedia($index): void
    {
        if (isset($this->media[$index])) {
            $newMedia = [];
            foreach ($this->media as $i => $file) {
                if ($i !== $index) $newMedia[] = $file;
            }
            $this->media = $newMedia;
        }
    }

    public function reorderLocalMedia($fromIndex, $toIndex): void
    {
        if (!is_array($this->media) || !isset($this->media[$fromIndex])) return;
        if ($toIndex < 0 || $toIndex >= count($this->media)) return;

        $item = $this->media[$fromIndex];
        array_splice($this->media, $fromIndex, 1);
        array_splice($this->media, $toIndex, 0, [$item]);
        $this->media = array_values($this->media);
    }

    public function updatedFormAiAnalysisEnabled($value)
    {
        if (!$value) {
            $this->include_client_logo = true;
            $this->include_client_header = true;
        }
    }

    public function browseNextcloud(string $path = '/')
    {
        $this->nextcloud_error = null;
        $service = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);

        if ($service->isConfigured()) {
            $this->nextcloud_browse_path = $path;
            $files = $service->listFiles($path, $this->nextcloud_media_kind);

            if ($files === null) {
                $this->nextcloud_error = 'Errore di lettura da Nextcloud (API/XML malformato).';
                $this->nextcloud_files = [];
            } elseif (empty($files)) {
                $this->nextcloud_files = [];
            } else {
                $this->nextcloud_files = $files;
            }
        } else {
            $this->nextcloud_error = 'Nextcloud non è configurato. Controlla il file .env.';
            $this->nextcloud_files = [];
        }
    }

    public function openNextcloudPicker(string $mediaKind = 'photo'): void
    {
        $this->nextcloud_media_kind = 'photo'; // Force photo
        $this->showNextcloudPicker = true;
        $this->pending_nextcloud_files = $this->selected_nextcloud_files;

        $service = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
        $startPath = $service->mediaRoot('photo');
        if ($this->campaign->client && !empty($this->campaign->client->nextcloud_photos_path)) {
            $startPath = $this->campaign->client->nextcloud_photos_path;
        }
        $this->browseNextcloud($startPath);
    }

    public function closeNextcloudPicker(): void
    {
        $this->showNextcloudPicker = false;
        $this->pending_nextcloud_files = [];
    }

    public function toggleNextcloudFile($path, $name, $size, $mime = null, $fileId = null): void
    {
        $existingIndex = collect($this->pending_nextcloud_files)->search(fn($f) => $f['path'] === $path);
        
        if ($existingIndex !== false) {
            unset($this->pending_nextcloud_files[$existingIndex]);
            $this->pending_nextcloud_files = array_values($this->pending_nextcloud_files);
        } else {
            $totalCount = count($this->existing_media) + (is_array($this->media) ? count($this->media) : 0) + count($this->pending_nextcloud_files);
            if ($totalCount >= 10) {
                $this->addError('form.nextcloud_path', 'Puoi avere al massimo 10 file totali.');
                return;
            }
            $this->pending_nextcloud_files[] = [
                'path' => $path,
                'name' => $name,
                'size' => $size,
                'mime' => $mime,
                'file_id' => $fileId,
            ];
        }
    }

    public function confirmNextcloudSelection(): void
    {
        if (empty($this->pending_nextcloud_files)) {
            $this->addError('form.nextcloud_path', 'Seleziona almeno una foto da Nextcloud.');
            return;
        }

        $this->selected_nextcloud_files = $this->pending_nextcloud_files;
        $this->form['nextcloud_path'] = $this->selected_nextcloud_files[0]['path'];
        $this->form['media_source'] = 'nextcloud';

        $this->showNextcloudPicker = false;
        $this->pending_nextcloud_files = [];
    }

    public function removeNextcloudFile($path = null): void
    {
        if ($path) {
            $this->selected_nextcloud_files = array_filter($this->selected_nextcloud_files, fn($f) => $f['path'] !== $path);
            $this->selected_nextcloud_files = array_values($this->selected_nextcloud_files);
            
            if (empty($this->selected_nextcloud_files)) {
                $this->form['nextcloud_path'] = null;
            } else {
                $this->form['nextcloud_path'] = $this->selected_nextcloud_files[0]['path'];
            }
        } else {
            $this->selected_nextcloud_files = [];
            $this->form['nextcloud_path'] = null;
        }
    }

    public function reorderNextcloudMedia($fromIndex, $toIndex): void
    {
        if (!isset($this->selected_nextcloud_files[$fromIndex])) return;
        if ($toIndex < 0 || $toIndex >= count($this->selected_nextcloud_files)) return;

        $item = $this->selected_nextcloud_files[$fromIndex];
        array_splice($this->selected_nextcloud_files, $fromIndex, 1);
        array_splice($this->selected_nextcloud_files, $toIndex, 0, [$item]);
        $this->selected_nextcloud_files = array_values($this->selected_nextcloud_files);
    }

    public function openNextcloudPreview(string $path): void
    {
        $file = collect($this->nextcloudFilesOnlyImages())
            ->firstWhere('path', $path);

        if (!$file) {
            return;
        }

        $this->preview_nextcloud_file = $file;
    }

    public function closeNextcloudPreview(): void
    {
        $this->preview_nextcloud_file = null;
    }

    public function previewNextcloudPrevious(): void
    {
        $files = $this->nextcloudFilesOnlyImages();
        $this->moveNextcloudPreview($files, -1);
    }

    public function previewNextcloudNext(): void
    {
        $files = $this->nextcloudFilesOnlyImages();
        $this->moveNextcloudPreview($files, 1);
    }

    private function moveNextcloudPreview(array $files, int $direction): void
    {
        if (!$this->preview_nextcloud_file || count($files) === 0) {
            return;
        }

        $currentPath = $this->preview_nextcloud_file['path'];

        $currentIndex = collect($files)->search(
            fn ($file) => $file['path'] === $currentPath
        );

        if ($currentIndex === false) {
            return;
        }

        $nextIndex = ($currentIndex + $direction + count($files)) % count($files);

        $this->preview_nextcloud_file = $files[$nextIndex];
    }

    private function nextcloudFilesOnlyImages(): array
    {
        return collect($this->nextcloud_files)
            ->filter(fn ($file) => empty($file['is_dir']) && ($file['is_image'] ?? false))
            ->values()
            ->all();
    }

    private function buildPostDataAndStoredMedia(array &$data): bool
    {
        $storedMedia = [];
        $baseSortOrder = collect($this->existing_media)->max('sort_order');
        $baseSortOrder = $baseSortOrder !== null ? $baseSortOrder + 1 : 0;

        $totalCount = count($this->existing_media) + 
            ($data['media_source'] === 'local' ? (is_array($this->media) ? count($this->media) : 0) : 0) + 
            ($data['media_source'] === 'nextcloud' ? count($this->selected_nextcloud_files) : 0);
            
        if ($totalCount > 10) {
            $this->addError('media', 'Il totale dei media non può superare i 10 elementi.');
            return false;
        }

        if ($data['media_source'] === 'local' && !empty($this->media)) {
            foreach ($this->media as $index => $uploadedFile) {
                $filename = \Illuminate\Support\Str::slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME))
                    . '_' . time() . '_' . $index . '.' . $uploadedFile->getClientOriginalExtension();
                
                $path = $uploadedFile->storeAs('marketing/campaign-posts', $filename, 'public');

                $storedMedia[] = [
                    'marketing_campaign_post_id' => $this->post->id,
                    'source' => 'local',
                    'path' => $path,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'mime_type' => $uploadedFile->getMimeType(),
                    'sort_order' => $baseSortOrder + $index,
                ];
            }
        } elseif ($data['media_source'] === 'nextcloud' && !empty($this->selected_nextcloud_files)) {
            $service = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
            foreach ($this->selected_nextcloud_files as $index => $ncFile) {
                $shareUrl = $service->createPublicShare($ncFile['path']);
                
                if (!$shareUrl) {
                    $this->addError('form.nextcloud_path', "Impossibile creare link pubblico per: {$ncFile['name']}");
                    return false;
                }

                $storedMedia[] = [
                    'marketing_campaign_post_id' => $this->post->id,
                    'source' => 'nextcloud',
                    'nextcloud_path' => $ncFile['path'],
                    'original_name' => $ncFile['name'] ?? basename($ncFile['path']),
                    'mime_type' => $ncFile['mime'] ?? null,
                    'nextcloud_file_id' => $ncFile['file_id'] ?? null,
                    'nextcloud_share_url' => $shareUrl,
                    'sort_order' => $baseSortOrder + $index,
                ];
            }
        }

        if (!empty($storedMedia)) {
            \App\Models\MarketingCampaignPostMedia::insert($storedMedia);
        }

        $this->loadExistingMedia();
        $this->syncLegacyMediaFields();

        // Pulizia state per permettere ulteriori modifiche senza duplicati
        $this->media = [];
        $this->selected_nextcloud_files = [];
        $this->form['nextcloud_path'] = null;
        $this->form['media_source'] = collect($this->existing_media)->first()['source'] ?? 'local';
        
        return true;
    }

    public function savePost()
    {
        $this->validate();

        $this->processClientIdentity();

        if (in_array($this->post->status, [
            MarketingCampaignPostStatus::ClientApproved,
            MarketingCampaignPostStatus::Approved,
            MarketingCampaignPostStatus::SubmittedToN8n,
            MarketingCampaignPostStatus::Published,
        ], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'post' => 'Non puoi modificare un post già approvato.',
            ]);
        }

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;

        $this->authorize('update', $this->post);
        $this->post->update($data); // first save normal data

        if (!$this->buildPostDataAndStoredMedia($data)) {
            return;
        }

        if ($this->post->currentVersion) {
            $mediaPayload = \App\Domain\Social\Builders\MarketingCampaignPostMediaPayloadBuilder::build($this->post);
            $imageUrls = collect($mediaPayload['media_items'] ?? [])
                ->pluck('url')
                ->filter()
                ->values()
                ->all();

            $this->post->currentVersion->update([
                'title' => $data['title'],
                'caption' => $data['description'],
                'image_url' => $imageUrls[0] ?? null,
                'image_urls' => $imageUrls,
            ]);
        }

        $this->dispatch('post-saved');
        $this->refreshPost();
    }

    public function saveAsManualVersion(): void
    {
        $this->authorize('update', $this->post);

        if ($this->post->current_version_id) {
            session()->flash('error', 'Il post ha già una versione attiva.');
            return;
        }

        if (! in_array($this->post->status->value, [
            MarketingCampaignPostStatus::Draft->value,
            MarketingCampaignPostStatus::ClientChangesRequested->value,
            MarketingCampaignPostStatus::Generated->value,
        ], true)) {
            session()->flash('error', 'Stato non valido per creare una versione manuale.');
            return;
        }

        $this->validate();
        $this->processClientIdentity();

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;

        $this->post->update($data);

        if (!$this->buildPostDataAndStoredMedia($data)) {
            return;
        }

        app(\App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction::class)
            ->execute($this->post->fresh(), auth()->user());

        $this->post->refresh();
        $this->dispatch('post-saved');
        $this->refreshPost();

        session()->flash('success', 'Post salvato come versione pronta senza Sody.');
    }

    public function saveAndSubmitToN8n(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction $submitAction)
    {
        $this->validate();

        $this->processClientIdentity();

        if ($this->post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile inviare a N8n un post già pubblicato.');
            return;
        }

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;
        $data['status'] = MarketingCampaignPostStatus::PendingN8n->value;

        $this->authorize('update', $this->post);
        $this->post->update($data);

        if (!$this->buildPostDataAndStoredMedia($data)) {
            return;
        }

        $this->showCancelRegenerationButton = false;
        $this->regeneration_timeout = false;
        $this->regeneration_checks = 0;

        $runtimeClientData = [
            'include_client_logo' => $this->include_client_logo,
            'include_client_header' => $this->include_client_header,
            'runtime_logo' => $this->runtime_logo,
            'runtime_activity_description' => $this->runtime_activity_description,
            'save_runtime_logo_to_client' => $this->save_runtime_logo_to_client,
            'save_runtime_activity_to_client' => $this->save_runtime_activity_to_client,
        ];

        try {
            $submitAction->execute($this->post, $runtimeClientData);
            $this->dispatch('post-submitted-n8n');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("N8n dispatch error: " . $e->getMessage());
        }

        $this->refreshPost();
    }

    public function regeneratePost(string $type, \App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction $action)
    {
        $this->authorize('update', $this->post);

        if ($this->post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile rigenerare un post già pubblicato.');
            return;
        }

        try {
            $this->showCancelRegenerationButton = false;
            $this->regeneration_timeout = false;
            $this->regeneration_checks = 0;
            
            $action->execute($this->post, auth()->user(), $type);
            $this->refreshPost();
            $this->dispatch('post-regenerating');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Marketing post regeneration failed', [
                'post_id' => $this->post->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            $this->addError('post', 'Errore rigenerazione: ' . $e->getMessage());
        }
    }

    public function sendToClient(\App\Domain\Social\Actions\SendMarketingCampaignPostToClientAction $action)
    {
        $this->authorize('update', $this->post);

        if ($this->post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile inviare in revisione un post già pubblicato.');
            return;
        }

        try {
            $token = $action->execute($this->post);
            $this->generatedReviewLink = route('public.marketing-campaign-posts.review', ['token' => $token->token]);
            $this->dispatch('post-sent-to-client');
            $this->refreshPost();
        } catch (\Exception $e) {
            $this->addError('post', $e->getMessage());
        }
    }

    public function approvePost()
    {
        $this->authorize('update', $this->post);

        if ($this->post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Post già pubblicato.');
            return;
        }

        $this->post->update([
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Approved->value,
        ]);

        $this->dispatch('post-approved');
        $this->refreshPost();
    }

    public function addInternalComment()
    {
        $this->validate(['newInternalComment' => 'required|string']);

        $this->authorize('update', $this->post);

        $this->post->comments()->create([
            'marketing_campaign_post_version_id' => $this->post->current_version_id,
            'user_id' => auth()->id(),
            'body' => $this->newInternalComment,
            'visibility' => \App\Enums\Social\MarketingCampaignPostCommentVisibility::Internal->value,
            'type' => \App\Enums\Social\MarketingCampaignPostCommentType::Comment->value,
        ]);

        $this->newInternalComment = '';
        $this->dispatch('internal-comment-added');
        $this->refreshPost();
    }

    public function deletePost()
    {
        $this->authorize('delete', $this->post);

        foreach ($this->post->mediaItems as $item) {
            if ($item->source === 'local' && $item->path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($item->path);
            }
        }
        $this->post->delete();

        return redirect()->route('marketing-campaigns.show', $this->campaign);
    }

    private function processClientIdentity()
    {
        $client = $this->campaign->client;
        $updated = false;

        if ($this->include_client_logo && $this->runtime_logo && ($this->save_runtime_logo_to_client || !$this->form['ai_analysis_enabled'])) {
            if ($this->runtime_logo instanceof \Illuminate\Http\UploadedFile) {
                $filename = 'logo_' . time() . '.' . $this->runtime_logo->getClientOriginalExtension();
                $path = $this->runtime_logo->storeAs('clients/logos', $filename, 'public');
                $client->logo_path = $path;
                $this->runtime_logo = null;
                $this->save_runtime_logo_to_client = false;
                $updated = true;
            }
        }

        if ($this->include_client_header && $this->runtime_activity_description && ($this->save_runtime_activity_to_client || !$this->form['ai_analysis_enabled'])) {
            $client->activity_description = $this->runtime_activity_description;
            $this->runtime_activity_description = null;
            $this->save_runtime_activity_to_client = false;
            $updated = true;
        }

        if ($updated) {
            $client->save();
        }
    }

    public function render()
    {
        return view('livewire.social.marketing-campaigns.marketing-campaign-post-show')
            ->layout('layouts.app');
    }
}
