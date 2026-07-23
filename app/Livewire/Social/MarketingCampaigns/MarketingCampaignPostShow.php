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
    public $all_local_media = []; 
    public array $selected_media_items = []; 
    public array $existing_media = []; // Legacy / reference

    // Nextcloud State
    public $nextcloud_media_kind = 'photo';
    public $nextcloud_browse_path = '/';
    public $nextcloud_files = [];
    public array $selected_nextcloud_files = [];
    public array $pending_nextcloud_files = [];
    public ?array $preview_nextcloud_file = null;
    public bool $showNextcloudPicker = false;
    public ?string $nextcloud_error = null;

    public array $preflightResults = [];

    // Regeneration state
    public bool $regeneration_timeout = false;
    public int $regeneration_checks = 0;
    public bool $showCancelRegenerationButton = false;

    public ?int $expectedCurrentVersionId = null;

    protected function rules()
    {
        return [
            'form.title' => 'nullable|string|max:255',
            'form.description' => 'nullable|string',
            'form.content_type' => ['required', \Illuminate\Validation\Rule::in(array_column(MarketingCampaignPostType::cases(), 'value'))],
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
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime',
                'max:204800',
            ],
            'runtime_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'runtime_activity_description' => 'nullable|string|max:1000',
        ];
    }

    public function mount(MarketingCampaign $campaign, MarketingCampaignPost $post)
    {
        $this->authorize('view', $post);
        abort_unless($post->marketing_campaign_id === $campaign->id, 404);
        $this->campaign = $campaign;

        // Eager load related data
        $post->load(['currentVersion', 'comments.user']);
        $this->post = $post;
        $this->expectedCurrentVersionId = $this->post->current_version_id;
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

        $this->refreshPreflight();
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
        $this->post->load(['currentVersion', 'currentVersion.mediaItems', 'comments.user']);
        $this->expectedCurrentVersionId = $this->post->current_version_id;
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
        $this->authorize('update', $this->post);

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

        $this->expectedCurrentVersionId = $this->post->current_version_id;

        $this->loadExistingMedia();
        $this->dispatch('marketing-post-regeneration-cancelled');
    }

    private function loadExistingMedia(bool $preserveUnsaved = true)
    {
        $resolvedMedia = $this->post->currentVersion 
            ? app(\App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver::class)->resolveMediaItems($this->post->currentVersion)
            : $this->post->orderedMediaItems;
            
        $this->existing_media = $resolvedMedia
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'source' => $item->source,
                    'path' => ($item->disk === 'public' && filled($item->path)) ? $item->path : $item->nextcloud_path,
                    'preview_url' => ($item->disk === 'public' && filled($item->path)) 
                        ? Storage::disk('public')->url($item->path)
                        : ($item->nextcloud_share_url ? $item->nextcloud_share_url . '/preview' : null),
                    'original_name' => $item->original_name,
                    'mime_type' => $item->mime_type,
                    'media_type' => $item->media_type,
                    'nextcloud_share_url' => $item->nextcloud_share_url,
                    'nextcloud_file_id' => $item->nextcloud_file_id,
                    'sort_order' => $item->sort_order,
                ];
            })->toArray();
            
        // Rebuild selected_media_items preserving local/nextcloud pending uploads if any
        $newSelected = [];
        foreach ($this->existing_media as $existing) {
            $isVid = ($existing['media_type'] ?? null) === 'video' || \Illuminate\Support\Str::startsWith($existing['mime_type'] ?? '', 'video/');
            $newSelected[] = [
                'uid' => 'existing:' . $existing['id'],
                'source' => 'existing',
                'existing_id' => $existing['id'],
                'type' => $isVid ? 'video' : 'image',
                'name' => $existing['original_name'],
                'preview_url' => $existing['preview_url'],
            ];
        }
        
        // Append current un-saved items (if any)
        if ($preserveUnsaved) {
            foreach ($this->selected_media_items as $item) {
                if ($item['source'] !== 'existing') {
                    $newSelected[] = $item;
                }
            }
        }
        $this->selected_media_items = $newSelected;
    }

    private function syncLegacyMediaFields()
    {
        // Now sync legacy media fields using selected_media_items first item if existing
        $first = collect($this->selected_media_items)->firstWhere('source', 'existing');
        if ($first) {
            $model = \App\Models\MarketingCampaignPostMedia::find($first['existing_id']);
            if ($model) {
                $isLocal = $model->disk === 'public' && filled($model->path);
                
                $this->post->update([
                    'media_path' => $isLocal ? $model->path : null,
                    'media_source' => $model->source,
                    'media_original_name' => $model->original_name,
                    'media_mime' => $model->mime_type,
                    'nextcloud_path' => !$isLocal ? $model->nextcloud_path : null,
                    'nextcloud_share_url' => !$isLocal ? $model->nextcloud_share_url : null,
                    'nextcloud_file_id' => !$isLocal ? $model->nextcloud_file_id : null,
                ]);
                $this->form['media_source'] = $model->source;
                $this->form['nextcloud_path'] = $model->source === 'nextcloud' ? $model->nextcloud_path : null;
            }
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

    public function removeSelectedMediaItem(string $uid): void
    {
        $this->authorize('update', $this->post);
        
        $item = collect($this->selected_media_items)->firstWhere('uid', $uid);
        if (!$item) return;

        $this->selected_media_items = array_values(array_filter($this->selected_media_items, fn($i) => $i['uid'] !== $uid));

        // Legacy sync for nextcloud files
        $this->selected_nextcloud_files = [];
        foreach ($this->selected_media_items as $i) {
            if ($i['source'] === 'nextcloud') {
                $this->selected_nextcloud_files[] = [
                    'path' => $i['nextcloud_path'],
                    'name' => $i['name'],
                    'size' => 0,
                    'mime' => $i['type'] === 'video' ? 'video/mp4' : 'image/jpeg',
                ];
            }
        }
        
        if (empty($this->selected_nextcloud_files)) {
            $this->form['nextcloud_path'] = null;
        } else {
            $this->form['nextcloud_path'] = $this->selected_nextcloud_files[0]['path'];
        }
    }

    public function reorderSelectedMedia(int $fromIndex, int $toIndex): void
    {
        if (!isset($this->selected_media_items[$fromIndex]) || $toIndex < 0 || $toIndex >= count($this->selected_media_items)) return;
        
        $item = array_splice($this->selected_media_items, $fromIndex, 1)[0];
        array_splice($this->selected_media_items, $toIndex, 0, [$item]);
        
        // Immediate reorder in DB for existing items if they changed order?
        // Let's just update all existing items sort_order based on current selected_media_items
        $this->syncSortOrderToDb();
    }

    private function syncSortOrderToDb()
    {
        // Now only syncs Livewire state, no longer updates DB directly
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
        $this->nextcloud_media_kind = $mediaKind;
        $this->showNextcloudPicker = true;
        $this->pending_nextcloud_files = [];

        $service = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
        $startPath = $service->mediaRoot($mediaKind);
        if ($this->campaign->client) {
            $clientPath = $mediaKind === 'video' ? $this->campaign->client->nextcloud_videos_path : $this->campaign->client->nextcloud_photos_path;
            if (!empty($clientPath)) {
                $startPath = $clientPath;
            }
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

        $alreadySelectedPaths = collect($this->selected_media_items)
            ->where('source', 'nextcloud')
            ->pluck('nextcloud_path')
            ->all();

        foreach ($this->pending_nextcloud_files as $ncFile) {
            if (in_array($ncFile['path'], $alreadySelectedPaths, true)) {
                continue;
            }

            if (count($this->selected_media_items) >= 10) {
                $this->addError('form.nextcloud_path', 'Hai raggiunto il limite massimo di 10 media.');
                break;
            }
            $isVid = !empty($ncFile['mime']) ? str_starts_with($ncFile['mime'], 'video/') : (preg_match('/\.(mp4|mov|m4v|webm|avi)$/i', $ncFile['name'] ?? '') === 1);
            $this->selected_media_items[] = [
                'uid' => 'nc:' . uniqid(),
                'source' => 'nextcloud',
                'type' => $isVid ? 'video' : 'image',
                'name' => $ncFile['name'] ?? basename($ncFile['path']),
                'nextcloud_path' => $ncFile['path'],
            ];
        }

        $this->syncLegacyPropertiesFromUnified();

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
        $file = collect($this->nextcloudFilesOnlyImagesOrVideos())
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
        $files = $this->nextcloudFilesOnlyImagesOrVideos();
        $this->moveNextcloudPreview($files, -1);
    }

    public function previewNextcloudNext(): void
    {
        $files = $this->nextcloudFilesOnlyImagesOrVideos();
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

    private function nextcloudFilesOnlyImagesOrVideos(): array
    {
        return collect($this->nextcloud_files)
            ->filter(fn ($file) => empty($file['is_dir']) && (($file['is_image'] ?? false) || ($file['is_video'] ?? false)))
            ->values()
            ->all();
    }
    public function getPendingMediaCountProperty(): int
    {
        return collect($this->selected_media_items)->filter(fn($i) => $i['source'] !== 'existing')->count();
    }

    public function hasVideoMedia(): bool
    {
        foreach ($this->selected_media_items as $item) {
            if ($item['type'] === 'video') return true;
        }
        return false;
    }

    public function hasPhotoMedia(): bool
    {
        foreach ($this->selected_media_items as $item) {
            if ($item['type'] === 'image') return true;
        }
        return false;
    }

    private function validateReelMedia(): bool
    {
        if ($this->form['content_type'] !== 'reel') return true;
        
        if (!$this->hasVideoMedia()) {
            $this->addError('media', 'Un Reel richiede almeno un file video.');
            return false;
        }

        return true;
    }

    private function hasPendingLocalMedia(): bool
    {
        return collect($this->selected_media_items)
            ->contains(fn ($item) => ($item['source'] ?? null) === 'local_pending');
    }

    private function buildPostDataAndStoredMedia(array &$data, array &$newlyCreatedFilePaths): array|false
    {
        $orderedMediaIds = [];
        $newlyCreatedMediaIds = [];

        $totalCount = count($this->selected_media_items);
            
        if ($totalCount > 10) {
            $this->addError('media', 'Il totale dei media non può superare i 10 elementi.');
            return false;
        }

        $service = null;
        if (collect($this->selected_media_items)->contains('source', 'nextcloud')) {
            $service = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
        }

        foreach ($this->selected_media_items as $index => $item) {
            if (($item['source'] ?? null) === 'local_pending') {
                $this->addError('media', 'Uno o più file locali non hanno ancora terminato il caricamento.');
                return false;
            }

            if ($item['source'] === 'existing') {
                $orderedMediaIds[] = $item['existing_id'];
                continue;
            }

            if ($item['source'] === 'local') {
                $uploadedFile = $this->all_local_media[$item['local_index']] ?? null;
                if (!$uploadedFile) continue;

                $filename = \Illuminate\Support\Str::slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME))
                    . '_' . time() . '_' . $index . '.' . $uploadedFile->getClientOriginalExtension();
                
                $path = $uploadedFile->storeAs('marketing/campaign-posts', $filename, 'public');
                $newlyCreatedFilePaths[] = $path;

                $newRecord = \App\Models\MarketingCampaignPostMedia::create([
                    'marketing_campaign_post_id' => $this->post->id,
                    'source' => 'local',
                    'disk' => 'public',
                    'media_type' => \App\Models\MarketingCampaignPostMedia::detectMediaType($uploadedFile->getMimeType()),
                    'path' => $path,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'mime_type' => $uploadedFile->getMimeType(),
                    'sort_order' => $index,
                ]);
                $orderedMediaIds[] = $newRecord->id;
                $newlyCreatedMediaIds[] = $newRecord->id;

            } elseif ($item['source'] === 'nextcloud') {
                $shareUrl = $service->createPublicShare($item['nextcloud_path']);
                
                if (!$shareUrl) {
                    $this->addError('form.nextcloud_path', "Impossibile creare link pubblico per: {$item['name']}");
                    return false;
                }

                $newRecord = \App\Models\MarketingCampaignPostMedia::create([
                    'marketing_campaign_post_id' => $this->post->id,
                    'source' => 'nextcloud',
                    'media_type' => $item['type'] === 'video' ? 'video' : 'image',
                    'nextcloud_path' => $item['nextcloud_path'],
                    'original_name' => $item['name'],
                    'mime_type' => null,
                    'nextcloud_file_id' => null,
                    'nextcloud_share_url' => $shareUrl,
                    'sort_order' => $index,
                ]);
                $orderedMediaIds[] = $newRecord->id;
                $newlyCreatedMediaIds[] = $newRecord->id;
            }
        }

        return [
            'ordered_media_ids' => $orderedMediaIds,
            'newly_created_media_ids' => $newlyCreatedMediaIds,
        ];
    }

    public function savePost()
    {
        $this->authorize('update', $this->post);

        if ($this->hasPendingLocalMedia()) {
            $this->addError('media', 'Attendi il completamento del caricamento dei file locali prima di salvare.');
            return;
        }

        $this->validate();

        if (!$this->validateReelMedia()) {
            return;
        }

        if (!$this->post->status->isManuallyEditable()) {
            $this->addError('post', 'Lo stato attuale non consente la modifica manuale.');
            return;
        }

        $data = $this->form;
        $preparedMedia = null;
        $newlyCreatedFilePaths = [];

        try {
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($data, &$preparedMedia, &$newlyCreatedFilePaths) {
                $preparedMedia = $this->buildPostDataAndStoredMedia($data, $newlyCreatedFilePaths);
                if ($preparedMedia === false) {
                    throw new \App\Exceptions\Social\MediaPreparationException();
                }

                // Metadati base (non cambiamo title, description e status direttamente qui se versionati)
                $metadataToUpdate = $data;
                unset($metadataToUpdate['title'], $metadataToUpdate['description'], $metadataToUpdate['status']);
                $this->post->update($metadataToUpdate);

                $dto = new \App\Domain\Social\DTOs\CreateManualMarketingCampaignPostVersionData(
                    expected_current_version_id: $this->expectedCurrentVersionId,
                    title: $data['title'] ?? null,
                    caption: $data['description'] ?? null,
                    hashtags: $this->post->currentVersion?->hashtags,
                    ordered_media_ids: $preparedMedia['ordered_media_ids'],
                    author_id: auth()->id()
                );

                $action = app(\App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction::class);
                return $action->execute($this->post, $dto);
            });

            if ($result->isCreated()) {
                session()->flash('success', 'Nuova versione creata.');
            } else {
                session()->flash('success', 'Nessuna modifica da salvare.');
            }

        } catch (\App\Exceptions\Social\StaleMarketingCampaignPostVersionException $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedFilePaths);
            session()->flash('error', $e->getMessage());
            return;
        } catch (\App\Exceptions\Social\MediaPreparationException $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedFilePaths);
            // Error already added to component
            return;
        } catch (\Exception $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedFilePaths);
            \Illuminate\Support\Facades\Log::error('Errore salvataggio manuale:', ['error' => $e->getMessage()]);
            session()->flash('error', 'Si è verificato un errore durante il salvataggio.');
            return;
        }

        try {
            $this->processClientIdentity();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Errore aggiornamento cliente post salvataggio versione:', ['error' => $e->getMessage()]);
            session()->flash('warning', 'Versione salvata, ma si è verificato un errore nell\'aggiornamento dei dati cliente.');
        }

        $this->post->refresh();
        $this->post->load(['currentVersion', 'currentVersion.mediaItems']);
        $this->expectedCurrentVersionId = $this->post->current_version_id;

        // Pulizia state per permettere ulteriori modifiche senza duplicati
        $this->selected_media_items = [];
        $this->loadExistingMedia(false);
        $this->media = [];
        $this->selected_nextcloud_files = [];
        $this->form['nextcloud_path'] = null;
        $this->form['media_source'] = collect($this->existing_media)->first()['source'] ?? 'local';

        $this->dispatch('post-saved');
        $this->refreshPost();
        $this->refreshPreflight();
    }

    private function cleanupCompensatoryFiles(array $paths)
    {
        foreach ($paths as $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
        }
        // TODO: Nextcloud shares cleanup non ancora disponibile in compensazione
    }

    public function saveAsManualVersion(): void
    {
        $this->savePost();
    }

    public function saveAndSubmitToN8n(string $generationType = 'full')
    {
        $submitAction = app(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction::class);
        if ($this->hasPendingLocalMedia()) {
            $this->addError('media', 'Attendi il completamento del caricamento dei file locali prima di salvare.');
            return;
        }

        $this->validate();

        if (!$this->validateReelMedia()) {
            return;
        }

        $this->processClientIdentity();

        if ($this->post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile inviare a N8n un post già pubblicato.');
            return;
        }

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;

        $this->authorize('update', $this->post);

        $preparedMedia = null;
        $newlyCreatedFilePaths = [];

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($data, &$preparedMedia, &$newlyCreatedFilePaths) {
                $preparedMedia = $this->buildPostDataAndStoredMedia($data, $newlyCreatedFilePaths);
                if ($preparedMedia === false) {
                    throw new \App\Exceptions\Social\MediaPreparationException();
                }

                // Metadati
                $metadataToUpdate = $data;
                $this->post->update($metadataToUpdate);
            });
        } catch (\App\Exceptions\Social\MediaPreparationException $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedFilePaths);
            return;
        } catch (\Exception $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedFilePaths);
            $this->addError('post', 'Errore durante il salvataggio: ' . $e->getMessage());
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
            'generation_type' => $generationType,
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

    public function publishToSocial(string $platform)
    {
        $this->authorize('update', $this->post);

        if (!in_array($this->post->status, [
            MarketingCampaignPostStatus::Approved,
            MarketingCampaignPostStatus::Failed,
        ])) {
            $this->addError('post', 'Stato del post non compatibile con la pubblicazione.');
            return;
        }

        try {
            if (in_array($platform, [\App\Enums\Social\SocialPlatform::Facebook->value, \App\Enums\Social\SocialPlatform::Instagram->value])) {
                $preflightService = app(\App\Domain\Social\Services\MetaPreflightService::class);
                $account = $this->post->campaign->client->socialAccountFor($platform);
                if ($account) {
                    $preflight = $preflightService->runPreflight($this->post, $account);
                    if (!$preflight->isPass) {
                        $this->addError('post', 'Preflight fallito: ' . implode(', ', $preflight->errors));
                        return;
                    }
                }
            } elseif ($platform === \App\Enums\Social\SocialPlatform::Tiktok->value) {
                $preflightService = app(\App\Domain\Social\Services\TikTokPreflightService::class);
                $account = $this->post->campaign->client->socialAccountFor($platform);
                if ($account) {
                    $preflight = $preflightService->runPreflight($this->post, $account);
                    if (!$preflight->isPass) {
                        $this->addError('post', 'TikTok Preflight fallito: ' . implode(', ', $preflight->errors));
                        return;
                    }
                }
            }

            // Dispatch async job
            \App\Jobs\Social\PublishMarketingCampaignPostJob::dispatch($this->post, $platform);
            
            session()->flash("success_publish_{$platform}", "Pubblicazione in corso su {$platform}... Il job è stato accodato.");
            $this->refreshPost();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Errore durante dispatch pubblicazione social', ['error' => $e->getMessage()]);
            $this->addError('post', 'Errore di sistema: ' . $e->getMessage());
        }
    }

    public function retryPublication(int $publicationId)
    {
        $this->authorize('update', clone $this->post);

        $publication = \App\Models\MarketingCampaignPostPublication::find($publicationId);
        if (!$publication) return;

        if ($publication->platform === \App\Enums\Social\SocialPlatform::Instagram && $publication->status === \App\Enums\Social\PublicationStatus::Failed) {
            $publication->update([
                'status' => \App\Enums\Social\PublicationStatus::Cancelled->value,
                'error_message' => 'Dismesso (sostituito da nuovo tentativo)',
            ]);
            
            app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($this->post);
            \App\Jobs\Social\PublishMarketingCampaignPostJob::dispatch($this->post, 'instagram');
            session()->flash("success_publish_{$publication->platform->value}", "Nuova pubblicazione Instagram avviata. Il vecchio container è stato scartato.");
            $this->refreshPost();
        } else {
            \App\Jobs\Social\PublishMarketingCampaignPostJob::dispatch($this->post, $publication->platform->value);
            session()->flash("success_publish_{$publication->platform->value}", "Riavvio forzato pubblicazione su {$publication->platform->value}.");
            $this->refreshPost();
        }
    }

    public function forceFailPublication(int $publicationId)
    {
        $this->authorize('update', clone $this->post);

        $publication = \App\Models\MarketingCampaignPostPublication::find($publicationId);
        // forceFailPublication is redundant if already failed, but kept for compatibility
        if ($publication && $publication->status === \App\Enums\Social\PublicationStatus::Failed) {
            $publication->update(['status' => \App\Enums\Social\PublicationStatus::Failed->value]);
            
            app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($this->post);
            session()->flash("error_publish_{$publication->platform->value}", "Post marcato come definitivamente fallito su {$publication->platform->value}.");
            $this->refreshPost();
        }
    }

    public function deletePost()
    {
        $this->authorize('delete', $this->post);

        try {
            app(\App\Domain\Social\Actions\DeleteMarketingCampaignPostAction::class)->execute($this->post);
        } catch (\Exception $e) {
            $this->addError('post', 'Impossibile eliminare il post: ' . $e->getMessage());
            return;
        }

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

    public function refreshPreflight(): void
    {
        $this->preflightResults = [];
        $platforms = [
            \App\Enums\Social\SocialPlatform::Instagram->value,
            \App\Enums\Social\SocialPlatform::Facebook->value,
            \App\Enums\Social\SocialPlatform::Tiktok->value,
        ];
        foreach ($platforms as $platform) {
            $account = $this->post->campaign->client->socialAccountFor($platform);
            if (!$account) {
                $this->preflightResults[$platform] = null;
                continue;
            }

            if (in_array($platform, [\App\Enums\Social\SocialPlatform::Facebook->value, \App\Enums\Social\SocialPlatform::Instagram->value])) {
                $this->preflightResults[$platform] = app(\App\Domain\Social\Services\MetaPreflightService::class)->runPreflight($this->post, $account);
            } elseif ($platform === \App\Enums\Social\SocialPlatform::Tiktok->value) {
                $this->preflightResults[$platform] = app(\App\Domain\Social\Services\TikTokPreflightService::class)->runPreflight($this->post, $account);
            }
        }
    }

    public function getPreflightResult(string $platform): ?\App\Domain\Social\Services\PreflightResult
    {
        return $this->preflightResults[$platform] ?? null;
    }

    public function registerPendingLocalMedia(array $filesMeta): void
    {
        foreach ($filesMeta as $meta) {
            if (count($this->selected_media_items) >= 10) {
                $this->addError('media', 'Hai raggiunto il limite massimo di 10 media.');
                break;
            }
            $this->selected_media_items[] = [
                'uid' => $meta['uid'],
                'source' => 'local_pending',
                'type' => $meta['type'],
                'name' => $meta['name'],
                'local_index' => null,
            ];
        }
    }

    public function updatedMedia()
    {
        if (!is_array($this->media)) return;

        foreach ($this->media as $uploadedFile) {
            $this->all_local_media[] = $uploadedFile;
            $localIndex = count($this->all_local_media) - 1;

            $pendingIndex = collect($this->selected_media_items)
                ->search(fn ($item) => ($item['source'] ?? null) === 'local_pending');

            if ($pendingIndex !== false) {
                $this->selected_media_items[$pendingIndex]['source'] = 'local';
                $this->selected_media_items[$pendingIndex]['local_index'] = $localIndex;
                continue;
            }

            $isVid = \Illuminate\Support\Str::startsWith($uploadedFile->getMimeType(), 'video/');
            $this->selected_media_items[] = [
                'uid' => 'local:' . uniqid(),
                'source' => 'local',
                'type' => $isVid ? 'video' : 'image',
                'name' => $uploadedFile->getClientOriginalName(),
                'local_index' => $localIndex,
            ];
        }

        $this->media = []; 
    }

    #[Computed]
    public function getPreviewMediaProperty(): array
    {
        return collect($this->selected_media_items)
            ->map(function ($item) {
                if ($item['source'] === 'existing') {
                    return [
                        'uid' => $item['uid'],
                        'type' => $item['type'],
                        'source' => 'existing',
                        'url' => $item['preview_url'],
                    ];
                }

                if ($item['source'] === 'local') {
                    $m = $this->all_local_media[$item['local_index']] ?? null;
                    if (!$m) return null;
                    $isVid = $item['type'] === 'video';
                    $url = method_exists($m, 'temporaryUrl') ? ($isVid ? $this->temporaryVideoPreviewUrl($m) . '#t=0.001' : $m->temporaryUrl()) : '';
                    return $url ? ['uid' => $item['uid'], 'type' => $item['type'], 'url' => $url, 'source' => 'local'] : null;
                }
                
                if ($item['source'] === 'nextcloud') {
                    $isVid = $item['type'] === 'video';
                    return [
                        'uid' => $item['uid'],
                        'type' => $item['type'],
                        'source' => 'nextcloud',
                        'url' => $isVid
                            ? route('nextcloud.download', ['path' => $item['nextcloud_path']]) . '#t=0.001'
                            : route('nextcloud.preview', ['path' => $item['nextcloud_path'], 'w' => 600, 'h' => 600]),
                    ];
                }
                
                if ($item['source'] === 'local_pending') {
                    return [
                        'uid' => $item['uid'],
                        'type' => $item['type'],
                        'source' => 'local_pending',
                        'url' => null,
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public function temporaryVideoPreviewUrl(\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string
    {
        return \Illuminate\Support\Facades\URL::signedRoute('social.temporary-video-preview', [
            'filename' => $file->getFilename(),
        ], now()->addMinutes(30));
    }
}
