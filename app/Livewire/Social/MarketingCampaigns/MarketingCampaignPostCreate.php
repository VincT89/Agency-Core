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

class MarketingCampaignPostCreate extends Component
{
    use WithFileUploads, AuthorizesRequests;

    public MarketingCampaign $campaign;

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

    // Client Identity for N8N Runtime
    public $include_client_logo = true;
    public $include_client_header = true;
    public $runtime_logo;
    public $runtime_activity_description;
    public $save_runtime_logo_to_client = false;
    public $save_runtime_activity_to_client = false;

    public $media = []; // Uploaded files (temporary inputs)
    public $all_local_media = []; // Accumulates TemporaryUploadedFiles
    public array $selected_media_items = []; // The unified source of truth

    // Nextcloud State
    public $nextcloud_media_kind = 'photo';
    public $nextcloud_browse_path = '/';
    public $nextcloud_files = [];
    public array $selected_nextcloud_files = [];
    public array $pending_nextcloud_files = [];
    public ?array $selected_nextcloud_file = null; // legacy
    public ?array $pending_nextcloud_file = null; // legacy
    public ?array $preview_nextcloud_file = null;
    public bool $showNextcloudPicker = false;
    public ?string $nextcloud_error = null;

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
            'media' => 'nullable|array|max:10',
            'media.*' => [
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime',
                'max:204800',
            ],
            'runtime_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'runtime_activity_description' => 'nullable|string|max:1000',
        ];
    }

    public function mount(MarketingCampaign $campaign)
    {
        $this->authorize('update', $campaign);

        $this->campaign = $campaign;

        $requestedDate = request()->query('date');

        $this->form['scheduled_date'] = $this->isValidCalendarDate($requestedDate)
            ? $requestedDate
            : now()->format('Y-m-d');
    }

    private function isValidCalendarDate(?string $date): bool
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d') === $date;
        } catch (\Throwable) {
            return false;
        }
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
            
            if (empty($files)) {
                $this->nextcloud_error = 'Nessun file trovato o impossibile leggere la cartella Nextcloud.';
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
            if (count($this->pending_nextcloud_files) >= 10) {
                $this->addError('form.nextcloud_path', 'Puoi selezionare al massimo 10 file Nextcloud.');
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

            $isVid = !empty($ncFile['mime']) ? str_starts_with($ncFile['mime'], 'video/') : (preg_match('/\.(mp4|mov|m4v|webm|avi)$/i', $ncFile['name']) === 1);

            $this->selected_media_items[] = [
                'uid' => 'nc:' . uniqid(),
                'source' => 'nextcloud',
                'type' => $isVid ? 'video' : 'image',
                'name' => $ncFile['name'],
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
                $this->selected_nextcloud_file = null;
                $this->form['nextcloud_path'] = null;
            } else {
                $this->selected_nextcloud_file = $this->selected_nextcloud_files[0];
                $this->form['nextcloud_path'] = $this->selected_nextcloud_file['path'];
            }
        } else {
            $this->selected_nextcloud_files = [];
            $this->selected_nextcloud_file = null;
            $this->form['nextcloud_path'] = null;
        }
    }

    public function openNextcloudPreview(string $path): void
    {
        $file = collect($this->nextcloudFilesOnlyImagesOrVideos())
            ->firstWhere('path', $path);

        if (!$file) {
            return;
        }

        $this->preview_nextcloud_file = $file;
        $this->pending_nextcloud_file = $file;
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
        $this->pending_nextcloud_file = $files[$nextIndex];
    }

    private function nextcloudFilesOnlyImagesOrVideos(): array
    {
        return collect($this->nextcloud_files)
            ->filter(fn ($file) => empty($file['is_dir']) && (($file['is_image'] ?? false) || ($file['is_video'] ?? false)))
            ->values()
            ->all();
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

    public function removeSelectedMediaItem(string $uid): void
    {
        $this->selected_media_items = array_values(array_filter($this->selected_media_items, fn($item) => $item['uid'] !== $uid));
        
        $this->syncLegacyPropertiesFromUnified();
    }

    public function reorderSelectedMedia(int $fromIndex, int $toIndex): void
    {
        if (!isset($this->selected_media_items[$fromIndex]) || $toIndex < 0 || $toIndex >= count($this->selected_media_items)) return;
        
        $item = array_splice($this->selected_media_items, $fromIndex, 1)[0];
        array_splice($this->selected_media_items, $toIndex, 0, [$item]);
        
        $this->syncLegacyPropertiesFromUnified();
    }

    private function syncLegacyPropertiesFromUnified(): void
    {
        $this->selected_nextcloud_files = [];
        foreach ($this->selected_media_items as $item) {
            if ($item['source'] === 'nextcloud') {
                $this->selected_nextcloud_files[] = [
                    'path' => $item['nextcloud_path'],
                    'name' => $item['name'],
                    'is_image' => $item['type'] === 'image',
                ];
            }
        }
        $this->selected_nextcloud_file = $this->selected_nextcloud_files[0] ?? null;
        
        if (!empty($this->selected_nextcloud_files)) {
            $this->form['nextcloud_path'] = $this->selected_nextcloud_file['path'];
        } else {
            $this->form['nextcloud_path'] = null;
        }
    }

    #[Computed]
    public function getPreviewMediaProperty(): array
    {
        return collect($this->selected_media_items)
            ->map(function ($item) {
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

    private function hasPendingLocalMedia(): bool
    {
        return collect($this->selected_media_items)
            ->contains(fn ($item) => ($item['source'] ?? null) === 'local_pending');
    }

    public function save()
    {
        if ($this->hasPendingLocalMedia()) {
            $this->addError('media', 'Attendi il completamento del caricamento dei file locali prima di salvare.');
            return;
        }

        $this->validate();


        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;
        $data['created_by'] = auth()->id();

        $storedMedia = [];

        if (!$this->buildPostDataAndStoredMedia($data, $storedMedia)) {
            return;
        }

        if (!$this->validateReelMedia($storedMedia)) {
            return;
        }

        $this->processClientIdentity();

        $post = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $storedMedia) {
            $post = MarketingCampaignPost::create($data);

            if (!empty($storedMedia)) {
                $post->mediaItems()->createMany($storedMedia);
            }

            return $post;
        });



        return redirect()->route('marketing-campaigns.posts.show', [
            'campaign' => $this->campaign->id,
            'post' => $post->id
        ]);
    }

    public function saveAndSubmitToN8n(string $generationType = 'full')
    {
        $submitAction = app(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction::class);
        if ($this->hasPendingLocalMedia()) {
            $this->addError('media', 'Attendi il completamento del caricamento dei file locali prima di salvare.');
            return;
        }

        $this->validate();
        

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;
        $data['created_by'] = auth()->id();

        $storedMedia = [];

        if (!$this->buildPostDataAndStoredMedia($data, $storedMedia)) {
            return;
        }

        if (!$this->validateReelMedia($storedMedia)) {
            return;
        }

        $this->processClientIdentity();

        $post = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $storedMedia) {
            $post = MarketingCampaignPost::create($data);

            if (!empty($storedMedia)) {
                $post->mediaItems()->createMany($storedMedia);
            }

            return $post;
        });

        $runtimeClientData = [
            'include_client_logo' => $this->include_client_logo,
            'include_client_header' => $this->include_client_header,
            'runtime_logo' => $this->runtime_logo,
            'runtime_activity_description' => $this->runtime_activity_description,
            'save_runtime_logo_to_client' => $this->save_runtime_logo_to_client,
            'save_runtime_activity_to_client' => $this->save_runtime_activity_to_client,
            'generation_type' => $generationType,
        ];

        $submitAction->execute($post, $runtimeClientData);

        return redirect()->route('marketing-campaigns.posts.show', [
            'campaign' => $this->campaign->id,
            'post' => $post->id
        ]);
    }

    public function getPendingMediaCountProperty(): int
    {
        return count($this->selected_media_items);
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

    private function validateReelMedia(array $storedMedia): bool
    {
        if ($this->form['content_type'] !== 'reel') return true;
        
        $hasVideo = false;
        foreach ($storedMedia as $media) {
            if (($media['media_type'] ?? '') === 'video') {
                $hasVideo = true;
                break;
            }
        }

        if (!$hasVideo) {
            $this->addError('media', 'Un Reel richiede almeno un file video.');
            return false;
        }

        return true;
    }

    private function buildPostDataAndStoredMedia(array &$data, array &$storedMedia): bool
    {
        $data['nextcloud_path'] = null;
        $data['nextcloud_share_url'] = null;
        $data['nextcloud_file_id'] = null;
        $data['media_path'] = null;

        $service = null;
        $hasNextcloud = collect($this->selected_media_items)->contains('source', 'nextcloud');
        if ($hasNextcloud) {
            $service = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
        }

        $legacyNextcloudFilled = false;
        $legacyLocalFilled = false;

        foreach ($this->selected_media_items as $index => $item) {
            if (($item['source'] ?? null) === 'local_pending') {
                $this->addError('media', 'Uno o più file locali non hanno ancora terminato il caricamento.');
                return false;
            }

            if ($item['source'] === 'local') {
                $uploadedFile = $this->all_local_media[$item['local_index']] ?? null;
                if (!$uploadedFile) continue;

                $filename = Str::slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME)) 
                            . '_' . time() . '_' . $index . '.' . $uploadedFile->getClientOriginalExtension();
                $path = $uploadedFile->storeAs('marketing/campaign-posts', $filename, 'public');

                $storedMedia[] = [
                    'source' => 'local',
                    'media_type' => \App\Models\MarketingCampaignPostMedia::detectMediaType($uploadedFile->getMimeType()),
                    'disk' => 'public',
                    'path' => $path,
                    'mime_type' => $uploadedFile->getMimeType(),
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'sort_order' => $index,
                ];

                if (!$legacyLocalFilled) {
                    $data['media_path'] = $path;
                    $data['media_original_name'] = $uploadedFile->getClientOriginalName();
                    $data['media_mime'] = $uploadedFile->getMimeType();
                    $legacyLocalFilled = true;
                }
            } elseif ($item['source'] === 'nextcloud') {
                $shareUrl = $service->createPublicShare($item['nextcloud_path']);
                if (!$shareUrl) {
                    $this->addError('form.nextcloud_path', "Impossibile creare il link pubblico Nextcloud per {$item['name']}.");
                    return false;
                }

                $storedMedia[] = [
                    'source' => 'nextcloud',
                    'media_type' => $item['type'] === 'video' ? 'video' : 'image',
                    'disk' => null,
                    'path' => null,
                    'mime_type' => null,
                    'original_name' => $item['name'],
                    'nextcloud_path' => $item['nextcloud_path'],
                    'nextcloud_share_url' => $shareUrl,
                    'nextcloud_file_id' => null,
                    'sort_order' => $index,
                ];

                if (!$legacyNextcloudFilled) {
                    $data['nextcloud_path'] = $item['nextcloud_path'];
                    $data['nextcloud_share_url'] = $shareUrl;
                    $data['nextcloud_file_id'] = null;
                    $legacyNextcloudFilled = true;
                }
            }
        }
        
        return true;
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
        return view('livewire.social.marketing-campaigns.marketing-campaign-post-create')
            ->layout('layouts.app');
    }

    public function temporaryVideoPreviewUrl(\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string
    {
        return \Illuminate\Support\Facades\URL::signedRoute('social.temporary-video-preview', [
            'filename' => $file->getFilename(),
        ], now()->addMinutes(30));
    }
}
