<?php

namespace App\Livewire\Social\MarketingCampaigns;

use App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction;
use App\Domain\Social\Actions\CreateMarketingCampaignPostPublicationAction;
use App\Domain\Social\Actions\DeleteMarketingCampaignPostAction;
use App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction;
use App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction;
use App\Domain\Social\Actions\SendMarketingCampaignPostToClientAction;
use App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Domain\Social\DTOs\CreateManualMarketingCampaignPostVersionData;
use App\Domain\Social\Exceptions\HistoricalPostProtectedException;
use App\Domain\Social\Exceptions\MarketingCampaignPostMediaResolutionException;
use App\Domain\Social\Services\MarketingCampaignPostMediaUploadPolicy;
use App\Domain\Social\Services\MarketingCampaignPostMediaUrlResolver;
use App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver;
use App\Domain\Social\Services\MediaIntegrityMetadataReader;
use App\Domain\Social\Services\MetaPreflightService;
use App\Domain\Social\Services\PreflightResult;
use App\Domain\Social\Services\TikTokPreflightService;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Enums\Social\MarketingCampaignPostCommentType;
use App\Enums\Social\MarketingCampaignPostCommentVisibility;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Exceptions\NextcloudShareException;
use App\Exceptions\Social\MediaPreparationException;
use App\Exceptions\Social\StaleMarketingCampaignPostVersionException;
use App\Jobs\Social\ExecuteMarketingCampaignPostPublicationJob;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class MarketingCampaignPostShow extends Component
{
    use AuthorizesRequests, WithFileUploads;

    private const TIKTOK_PRIVACY_LEVELS = [
        'PUBLIC_TO_EVERYONE',
        'MUTUAL_FOLLOW_FRIENDS',
        'FOLLOWER_OF_CREATOR',
        'SELF_ONLY',
    ];

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

    public array $tiktokDirectOptions = [
        'privacy_level' => '',
        'allow_comment' => false,
        'allow_duet' => false,
        'allow_stitch' => false,
        'commercial_content' => false,
        'brand_organic_toggle' => false,
        'brand_content_toggle' => false,
        'is_aigc' => false,
        'consent' => false,
    ];

    public array $tiktokCreatorInfo = [];

    public ?string $tiktokDirectOptionsError = null;

    // Regeneration state
    public bool $regeneration_timeout = false;

    public int $regeneration_checks = 0;

    public bool $showCancelRegenerationButton = false;

    public ?int $expectedCurrentVersionId = null;

    public bool $mediaResolutionFailed = false;

    private MarketingCampaignPostVersionMediaResolver $mediaResolver;

    private MarketingCampaignPostMediaUrlResolver $urlResolver;

    public function boot(
        MarketingCampaignPostVersionMediaResolver $mediaResolver,
        MarketingCampaignPostMediaUrlResolver $urlResolver
    ): void {
        $this->mediaResolver = $mediaResolver;
        $this->urlResolver = $urlResolver;
    }

    protected function rules()
    {
        return [
            'form.title' => 'nullable|string|max:255',
            'form.description' => 'nullable|string',
            'form.content_type' => ['required', Rule::in(array_column(MarketingCampaignPostType::cases(), 'value'))],
            'form.scheduled_date' => 'nullable|date',
            'form.scheduled_time' => 'nullable|date_format:H:i',
            'form.status' => ['required', Rule::in(array_column(MarketingCampaignPostStatus::cases(), 'value'))],
            'form.ai_analysis_enabled' => 'boolean',
            'form.media_source' => ['required', Rule::in(['local', 'nextcloud'])],
            'form.nextcloud_path' => 'nullable|string|max:255',
            'form.publishing_platforms' => 'nullable|array',
            'form.publishing_platforms.*' => 'string|in:instagram,facebook,tiktok',
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => MarketingCampaignPostMediaUploadPolicy::validationRules(),
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

        $this->loadTikTokDirectOptions();
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

            if ($this->post->n8n_error) {
                $this->dispatch(
                    'sody-processing-failed',
                    message: 'Sody non ha completato la richiesta. Controlla il post e riprova.'
                );
            } else {
                $this->dispatch('sody-processing-completed');
            }

            $this->regeneration_timeout = false;
            $this->regeneration_checks = 0;

            return;
        }

        if (in_array($this->post->status->value, ['pending_n8n', 'submitted_to_n8n', 'regenerating'])) {
            $this->regeneration_checks++;
            if ($this->regeneration_checks >= 10) {
                $this->dispatch('sody-processing-delayed');
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
            ?? MarketingCampaignPostStatus::Generated->value;

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
        $this->mediaResolutionFailed = false;
        try {
            $resolvedMedia = $this->mediaResolver->resolveForPost($this->post)->mediaItems;
        } catch (MarketingCampaignPostMediaResolutionException $e) {
            $resolvedMedia = collect();
            $this->mediaResolutionFailed = true;
            $this->addError('media', 'Media non disponibili.');
            Log::error('social.version_media.resolution_failed_in_show', [
                'marketing_campaign_post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->existing_media = $resolvedMedia
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'source' => $item->source,
                    'path' => (in_array($item->disk, ['public', 'social_media'], true) && filled($item->path))
                        ? $item->path
                        : $item->nextcloud_path,
                    'preview_url' => $this->urlResolver->previewUrl($item),
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
            $isVid = ($existing['media_type'] ?? null) === 'video' || Str::startsWith($existing['mime_type'] ?? '', 'video/');
            $newSelected[] = [
                'uid' => 'existing:'.$existing['id'],
                'source' => 'existing',
                'existing_id' => $existing['id'],
                'type' => $isVid ? 'video' : 'image',
                'name' => $existing['original_name'],
                'preview_url' => $existing['preview_url'],
                'mime_type' => $existing['mime_type'],
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
            $model = MarketingCampaignPostMedia::find($first['existing_id']);
            if ($model) {
                $isLocal = in_array(
                    $model->disk,
                    ['public', 'social_media'],
                    true
                ) && filled($model->path);

                $this->post->update([
                    'media_path' => $isLocal ? $model->path : null,
                    'media_source' => $model->source,
                    'media_original_name' => $model->original_name,
                    'media_mime' => $model->mime_type,
                    'nextcloud_path' => ! $isLocal ? $model->nextcloud_path : null,
                    'nextcloud_share_url' => ! $isLocal ? $model->nextcloud_share_url : null,
                    'nextcloud_file_id' => ! $isLocal ? $model->nextcloud_file_id : null,
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
        if (! $item) {
            return;
        }

        $this->selected_media_items = array_values(array_filter($this->selected_media_items, fn ($i) => $i['uid'] !== $uid));

        $this->syncLegacyPropertiesFromUnified();
    }

    private function syncLegacyPropertiesFromUnified(): void
    {
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
        if (! isset($this->selected_media_items[$fromIndex]) || $toIndex < 0 || $toIndex >= count($this->selected_media_items)) {
            return;
        }

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
        if (! $value) {
            $this->include_client_logo = true;
            $this->include_client_header = true;
        }
    }

    public function browseNextcloud(string $path = '/')
    {
        $this->nextcloud_error = null;
        $service = app(NextcloudService::class);

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

        $service = app(NextcloudService::class);
        $startPath = $service->mediaRoot($mediaKind);
        if ($this->campaign->client) {
            $clientPath = $mediaKind === 'video' ? $this->campaign->client->nextcloud_videos_path : $this->campaign->client->nextcloud_photos_path;
            if (! empty($clientPath)) {
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
        $existingIndex = collect($this->pending_nextcloud_files)->search(fn ($f) => $f['path'] === $path);

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
            $isVid = ! empty($ncFile['mime']) ? str_starts_with($ncFile['mime'], 'video/') : (preg_match('/\.(mp4|mov|m4v|webm|avi)$/i', $ncFile['name'] ?? '') === 1);
            $this->selected_media_items[] = [
                'uid' => 'nc:'.uniqid(),
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
            $this->selected_nextcloud_files = array_filter($this->selected_nextcloud_files, fn ($f) => $f['path'] !== $path);
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
        if (! isset($this->selected_nextcloud_files[$fromIndex])) {
            return;
        }
        if ($toIndex < 0 || $toIndex >= count($this->selected_nextcloud_files)) {
            return;
        }

        $item = $this->selected_nextcloud_files[$fromIndex];
        array_splice($this->selected_nextcloud_files, $fromIndex, 1);
        array_splice($this->selected_nextcloud_files, $toIndex, 0, [$item]);
        $this->selected_nextcloud_files = array_values($this->selected_nextcloud_files);
    }

    public function openNextcloudPreview(string $path): void
    {
        $file = collect($this->nextcloudFilesOnlyImagesOrVideos())
            ->firstWhere('path', $path);

        if (! $file) {
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
        if (! $this->preview_nextcloud_file || count($files) === 0) {
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
        return collect($this->selected_media_items)->filter(fn ($i) => $i['source'] !== 'existing')->count();
    }

    public function hasVideoMedia(): bool
    {
        foreach ($this->selected_media_items as $item) {
            if ($item['type'] === 'video') {
                return true;
            }
        }

        return false;
    }

    public function hasPhotoMedia(): bool
    {
        foreach ($this->selected_media_items as $item) {
            if ($item['type'] === 'image') {
                return true;
            }
        }

        return false;
    }

    private function validateReelMedia(): bool
    {
        if ($this->form['content_type'] !== 'reel') {
            return true;
        }

        if (! $this->hasVideoMedia()) {
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

    private function buildPostDataAndStoredMedia(array &$data, array &$newlyCreatedFilePaths, array &$newlyCreatedNextcloudShares, ?NextcloudService $service): array|false
    {
        $orderedMediaIds = [];
        $newlyCreatedMediaIds = [];

        $totalCount = count($this->selected_media_items);

        if ($totalCount > 10) {
            $this->addError('media', 'Il totale dei media non può superare i 10 elementi.');

            return false;
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
                if (! $uploadedFile) {
                    $this->addError('media', 'Un file locale non è più disponibile. Rimuovilo e caricalo di nuovo.');

                    return false;
                }

                $filename = Str::slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME))
                    .'_'.Str::uuid()->toString().'.'.$uploadedFile->getClientOriginalExtension();

                $path = $uploadedFile->storeAs('marketing/campaign-posts', $filename, 'social_media');
                $newlyCreatedFilePaths[] = $path;
                $integrity = app(
                    MediaIntegrityMetadataReader::class
                )->readLocal('social_media', $path);

                $newRecord = MarketingCampaignPostMedia::create([
                    'marketing_campaign_post_id' => $this->post->id,
                    'source' => 'local',
                    'disk' => 'social_media',
                    'media_type' => MarketingCampaignPostMedia::detectMediaType($integrity['mime_type']),
                    'path' => $path,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'mime_type' => $integrity['mime_type'],
                    'source_size_bytes' => $integrity['source_size_bytes'],
                    'sha256' => $integrity['sha256'],
                    'sort_order' => $index,
                ]);
                $orderedMediaIds[] = $newRecord->id;
                $newlyCreatedMediaIds[] = $newRecord->id;

            } elseif ($item['source'] === 'nextcloud') {
                try {
                    $fileInfo = $service->getFileInfo($item['nextcloud_path']);
                    $shareResult = $service->ensurePublicShare($item['nextcloud_path']);
                    if ($shareResult->created) {
                        $newlyCreatedNextcloudShares[] = $shareResult->shareId;
                    }

                    $newRecord = MarketingCampaignPostMedia::create([
                        'marketing_campaign_post_id' => $this->post->id,
                        'source' => 'nextcloud',
                        'media_type' => MarketingCampaignPostMedia::detectMediaType($fileInfo->mimeType),
                        'nextcloud_path' => $fileInfo->path,
                        'original_name' => $item['name'] ?? basename($item['nextcloud_path']),
                        'mime_type' => $fileInfo->mimeType,
                        'source_size_bytes' => $fileInfo->sizeBytes,
                        'nextcloud_file_id' => $fileInfo->fileId,
                        'nextcloud_etag' => $fileInfo->etag,
                        'nextcloud_share_url' => $shareResult->url,
                        'sort_order' => $index,
                    ]);
                    $orderedMediaIds[] = $newRecord->id;
                    $newlyCreatedMediaIds[] = $newRecord->id;
                } catch (NextcloudShareException $e) {
                    Log::warning('Unable to prepare Nextcloud media for marketing post', [
                        'post_id' => $this->post->id,
                        'path' => $item['nextcloud_path'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    $this->addError(
                        'form.nextcloud_path',
                        'Non riesco a preparare uno dei file selezionati da Nextcloud. Selezionalo di nuovo e riprova.'
                    );

                    return false;
                }
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

        if ($this->mediaResolutionFailed) {
            $this->addError('media', 'Alcuni file del post non sono più disponibili. Rimuovili e selezionali di nuovo.');

            return;
        }

        if ($this->hasPendingLocalMedia()) {
            $this->addError('media', 'Attendi il completamento del caricamento dei file locali prima di salvare.');

            return;
        }

        if (empty($this->selected_media_items)) {
            $this->addError('media', 'Aggiungi almeno un media prima di salvare il post come pronto.');

            return;
        }

        $this->validate();

        if (! $this->validateReelMedia()) {
            return;
        }

        if (! $this->post->status->isManuallyEditable()) {
            $this->addError('post', 'Lo stato attuale non consente la modifica manuale.');

            return;
        }

        $data = $this->form;
        $preparedMedia = null;
        $newlyCreatedMediaPaths = [];
        $newlyCreatedNextcloudShares = [];
        $locks = [];
        $ncService = null;

        $hasNextcloud = collect($this->selected_media_items)->contains('source', 'nextcloud');
        if ($hasNextcloud) {
            $ncService = app(NextcloudService::class);
            $ncPaths = collect($this->selected_media_items)
                ->where('source', 'nextcloud')
                ->pluck('nextcloud_path')
                ->all();

            try {
                $locks = $ncService->acquireLocksForPaths($ncPaths);
            } catch (NextcloudShareException $e) {
                Log::warning('Unable to reserve Nextcloud media while saving marketing post', [
                    'post_id' => $this->post->id,
                    'error' => $e->getMessage(),
                ]);
                $this->addError(
                    'form.nextcloud_path',
                    'I file selezionati da Nextcloud non sono disponibili in questo momento. Riprova tra poco.'
                );

                return;
            }
        }

        try {
            $result = DB::transaction(function () use ($data, &$preparedMedia, &$newlyCreatedMediaPaths, &$newlyCreatedNextcloudShares, $ncService) {
                $preparedMedia = $this->buildPostDataAndStoredMedia($data, $newlyCreatedMediaPaths, $newlyCreatedNextcloudShares, $ncService);
                if ($preparedMedia === false) {
                    throw new MediaPreparationException;
                }

                // Metadati base (non cambiamo title, description e status direttamente qui se versionati)
                $metadataToUpdate = $data;
                unset($metadataToUpdate['title'], $metadataToUpdate['description'], $metadataToUpdate['status']);
                $this->post->update($metadataToUpdate);

                $dto = new CreateManualMarketingCampaignPostVersionData(
                    expected_current_version_id: $this->expectedCurrentVersionId,
                    title: $data['title'] ?? null,
                    caption: $data['description'] ?? null,
                    hashtags: $this->post->currentVersion?->hashtags,
                    ordered_media_ids: $preparedMedia['ordered_media_ids'],
                    author_id: auth()->id()
                );

                $action = app(CreateManualMarketingCampaignPostVersionAction::class);

                return $action->execute($this->post, $dto);
            });

            if ($result->isCreated()) {
                session()->flash('success', 'Nuova versione creata.');
            } else {
                session()->flash('success', 'Nessuna modifica da salvare.');
            }

        } catch (StaleMarketingCampaignPostVersionException $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedMediaPaths, $newlyCreatedNextcloudShares);
            $this->addError('post', 'Il post è stato modificato in un\'altra sessione. Ricarica la pagina prima di continuare.');

            return;
        } catch (MediaPreparationException $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedMediaPaths, $newlyCreatedNextcloudShares);

            // Error already added to component
            return;
        } catch (\Exception $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedMediaPaths, $newlyCreatedNextcloudShares);
            Log::error('Errore salvataggio manuale:', ['error' => $e->getMessage()]);
            session()->flash('error', 'Si è verificato un errore durante il salvataggio.');

            return;
        } finally {
            if ($ncService && ! empty($locks)) {
                $ncService->releaseLocks($locks);
            }
        }

        $newlyCreatedClientIdentityPaths = [];
        try {
            $oldLogoPathToClean = null;
            $logoUpdated = false;
            $activityUpdated = false;

            DB::transaction(function () use (&$newlyCreatedClientIdentityPaths, &$oldLogoPathToClean, &$logoUpdated, &$activityUpdated) {
                $client = $this->campaign->client()->lockForUpdate()->first();
                $clientUpdates = $this->prepareClientIdentity($newlyCreatedClientIdentityPaths);

                if (! empty($clientUpdates)) {
                    if (isset($clientUpdates['logo_path'])) {
                        $oldLogoPathToClean = $client->logo_path;
                        $logoUpdated = true;
                    }
                    if (isset($clientUpdates['activity_description'])) {
                        $activityUpdated = true;
                    }
                    $client->fill($clientUpdates);
                    $client->save();
                }
            });

            $this->commitClientIdentityUpdates($logoUpdated, $activityUpdated, $oldLogoPathToClean);
        } catch (\Throwable $e) {
            $this->cleanupCompensatoryFiles(
                $newlyCreatedClientIdentityPaths,
                [],
                'public'
            );
            Log::warning('Errore aggiornamento cliente post salvataggio versione:', ['error' => $e->getMessage()]);
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

    private function cleanupCompensatoryFiles(
        array $paths,
        array $nextcloudShares = [],
        string $disk = 'social_media'
    ) {
        foreach ($paths as $path) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete temporary local file during cleanup', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! empty($nextcloudShares)) {
            $ncService = app(NextcloudService::class);
            foreach ($nextcloudShares as $shareId) {
                try {
                    $ncService->revokePublicShareById($shareId);
                } catch (\Throwable $e) {
                    Log::warning('Failed to revoke Nextcloud share during cleanup', [
                        'shareId' => $shareId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function saveAsManualVersion(): void
    {
        if ((bool) ($this->form['ai_analysis_enabled'] ?? true)) {
            $this->addError('post', 'Disattiva Richiedi Analisi Sody per salvare il post senza Sody.');

            return;
        }

        $this->savePost();
    }

    private function notifySodyFailure(string $message, ?string $field = null): void
    {
        if ($field !== null) {
            $this->addError($field, $message);
        }

        $this->dispatch('sody-processing-failed', message: $message);
    }

    public function saveAndSubmitToN8n(string $generationType = 'full')
    {
        $this->dispatch('sody-processing-started');

        $submitAction = app(SubmitMarketingCampaignPostToN8nAction::class);

        if ($this->mediaResolutionFailed) {
            $this->notifySodyFailure(
                'Alcuni file del post non sono più disponibili. Rimuovili e selezionali di nuovo.',
                'media'
            );

            return;
        }

        if ($this->hasPendingLocalMedia()) {
            $this->notifySodyFailure(
                'Attendi che il caricamento dei file sia terminato prima di avviare Sody.',
                'media'
            );

            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->notifySodyFailure('Controlla i campi evidenziati e riprova.');

            throw $e;
        }

        if (! $this->validateReelMedia()) {
            $this->notifySodyFailure('Controlla i file selezionati per il Reel e riprova.');

            return;
        }

        if ($this->post->status === MarketingCampaignPostStatus::Published) {
            $this->notifySodyFailure(
                'Un post già pubblicato non può essere inviato nuovamente a Sody.',
                'post'
            );

            return;
        }

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;

        $this->authorize('update', $this->post);

        $preparedMedia = null;
        $newlyCreatedMediaPaths = [];
        $newlyCreatedClientIdentityPaths = [];
        $newlyCreatedNextcloudShares = [];

        $service = null;
        $locks = [];
        $hasNextcloud = collect($this->selected_media_items)->contains('source', 'nextcloud');

        if ($hasNextcloud) {
            $service = app(NextcloudService::class);
            $paths = collect($this->selected_media_items)
                ->where('source', 'nextcloud')
                ->pluck('nextcloud_path')
                ->all();

            try {
                $locks = $service->acquireLocksForPaths($paths);
            } catch (NextcloudShareException $e) {
                Log::warning('Unable to reserve Nextcloud media before Sody submission', [
                    'post_id' => $this->post->id,
                    'error' => $e->getMessage(),
                ]);
                $this->notifySodyFailure(
                    'I file selezionati da Nextcloud non sono disponibili in questo momento. Riprova tra poco.',
                    'media'
                );

                return;
            }
        }

        try {
            $oldLogoPathToClean = null;
            $logoUpdated = false;
            $activityUpdated = false;

            DB::transaction(function () use ($data, &$preparedMedia, &$newlyCreatedMediaPaths, &$newlyCreatedClientIdentityPaths, &$newlyCreatedNextcloudShares, $service, &$oldLogoPathToClean, &$logoUpdated, &$activityUpdated) {
                $client = $this->campaign->client()->lockForUpdate()->first();
                $clientUpdates = $this->prepareClientIdentity($newlyCreatedClientIdentityPaths);

                if (! empty($clientUpdates)) {
                    if (isset($clientUpdates['logo_path'])) {
                        $oldLogoPathToClean = $client->logo_path;
                        $logoUpdated = true;
                    }
                    if (isset($clientUpdates['activity_description'])) {
                        $activityUpdated = true;
                    }
                    $client->fill($clientUpdates);
                    $client->save();
                }

                $preparedMedia = $this->buildPostDataAndStoredMedia($data, $newlyCreatedMediaPaths, $newlyCreatedNextcloudShares, $service);
                if ($preparedMedia === false) {
                    throw new MediaPreparationException;
                }

                // Metadati
                $metadataToUpdate = $data;
                unset($metadataToUpdate['status']); // We don't update status directly here, action does it
                $this->post->update($metadataToUpdate);
            });

            $this->commitClientIdentityUpdates($logoUpdated, $activityUpdated, $oldLogoPathToClean);
        } catch (MediaPreparationException $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedMediaPaths, $newlyCreatedNextcloudShares);
            $this->cleanupCompensatoryFiles(
                $newlyCreatedClientIdentityPaths,
                [],
                'public'
            );
            $this->notifySodyFailure('Non riesco a preparare i file selezionati. Controllali e riprova.');

            return;
        } catch (\Exception $e) {
            $this->cleanupCompensatoryFiles($newlyCreatedMediaPaths, $newlyCreatedNextcloudShares);
            $this->cleanupCompensatoryFiles(
                $newlyCreatedClientIdentityPaths,
                [],
                'public'
            );
            Log::error('Unable to save marketing post before Sody submission', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);
            $this->notifySodyFailure(
                'Non è stato possibile salvare il post. Riprova tra poco.',
                'post'
            );

            return;
        } finally {
            if ($service && ! empty($locks)) {
                $service->releaseLocks($locks);
            }
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
            Log::error('Sody submission dispatch failed', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);
            $this->notifySodyFailure(
                'Il post è stato salvato, ma Sody non è stata avviata. Riprova tra poco.',
                'post'
            );
        }

        $this->refreshPost();
    }

    public function regeneratePost(string $type, RequestMarketingCampaignPostRegenerationAction $action)
    {
        $this->dispatch('sody-processing-started');
        $this->authorize('update', $this->post);

        if ($this->post->status === MarketingCampaignPostStatus::Published) {
            $this->notifySodyFailure(
                'Un post già pubblicato non può essere rigenerato.',
                'post'
            );

            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->notifySodyFailure('Controlla i campi evidenziati e riprova.');

            throw $e;
        }

        $metadataToUpdate = [
            'title' => $this->form['title'] ?? null,
            'description' => $this->form['description'] ?? null,
        ];
        $this->post->update(array_filter($metadataToUpdate, fn ($val) => ! is_null($val)));

        try {
            $this->showCancelRegenerationButton = false;
            $this->regeneration_timeout = false;
            $this->regeneration_checks = 0;

            $action->execute($this->post, auth()->user(), $type);
            $this->refreshPost();
            $this->dispatch('post-regenerating');
        } catch (\Exception $e) {
            Log::error('Marketing post regeneration failed', [
                'post_id' => $this->post->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            $this->notifySodyFailure(
                'Sody non è stata avviata. Riprova tra poco.',
                'post'
            );
        }
    }

    public function sendToClient(SendMarketingCampaignPostToClientAction $action)
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
            Log::warning('Unable to send marketing post for client review', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);
            $this->addError('post', 'Non è stato possibile preparare il link di revisione. Riprova tra poco.');
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
            'status' => MarketingCampaignPostStatus::Approved->value,
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
            'visibility' => MarketingCampaignPostCommentVisibility::Internal->value,
            'type' => MarketingCampaignPostCommentType::Comment->value,
        ]);

        $this->newInternalComment = '';
        $this->dispatch('internal-comment-added');
        $this->refreshPost();
    }

    public function refreshTikTokDirectOptions(): void
    {
        $this->authorize('update', $this->post);
        $this->resetValidation();
        $this->loadTikTokDirectOptions(forceRefresh: true);
    }

    public function updatedTiktokDirectOptions(mixed $value, string $key): void
    {
        if ($key === 'commercial_content' && ! (bool) $value) {
            $this->tiktokDirectOptions['brand_organic_toggle'] = false;
            $this->tiktokDirectOptions['brand_content_toggle'] = false;
        }

        if (
            $key === 'privacy_level'
            && ! in_array((string) $value, [
                'PUBLIC_TO_EVERYONE',
                'MUTUAL_FOLLOW_FRIENDS',
            ], true)
        ) {
            $this->tiktokDirectOptions['brand_content_toggle'] = false;
        }
    }

    private function loadTikTokDirectOptions(bool $forceRefresh = false): void
    {
        $this->tiktokDirectOptions = [
            'privacy_level' => '',
            'allow_comment' => false,
            'allow_duet' => false,
            'allow_stitch' => false,
            'commercial_content' => false,
            'brand_organic_toggle' => false,
            'brand_content_toggle' => false,
            'is_aigc' => false,
            'consent' => false,
        ];
        $this->tiktokCreatorInfo = [];
        $this->tiktokDirectOptionsError = null;

        if (
            config('services.tiktok.delivery_mode') !== 'direct'
            || ! in_array(SocialPlatform::Tiktok->value, $this->form['publishing_platforms'] ?? [], true)
        ) {
            return;
        }

        if (! config('services.tiktok.direct_publish_enabled', false)) {
            $this->tiktokDirectOptionsError = 'La pubblicazione diretta TikTok non è abilitata sul server.';

            return;
        }

        $account = $this->campaign->client->socialAccountFor(SocialPlatform::Tiktok->value);
        if (! $account || ! $account->isApiConnected()) {
            $this->tiktokDirectOptionsError = 'Collega nuovamente l\'account TikTok prima di pubblicare.';

            return;
        }

        $scopes = is_array($account->scopes) ? $account->scopes : [];
        if (! in_array('video.publish', $scopes, true)) {
            $this->tiktokDirectOptionsError = 'L\'account TikTok deve essere ricollegato autorizzando la pubblicazione diretta.';

            return;
        }

        try {
            $creatorInfo = app(TikTokContentPostingService::class)->queryCreatorInfo(
                $account->access_token,
                (string) $account->id,
                $forceRefresh
            );

            $privacyLevels = array_values(array_filter(
                $creatorInfo['privacy_level_options'] ?? [],
                fn (mixed $level): bool => is_string($level)
                    && in_array($level, self::TIKTOK_PRIVACY_LEVELS, true)
            ));
            $creatorNickname = trim((string) ($creatorInfo['creator_nickname'] ?? ''));

            if ($privacyLevels === [] || $creatorNickname === '') {
                throw new \UnexpectedValueException('TikTok non ha restituito tutte le informazioni del creator.');
            }

            $this->tiktokCreatorInfo = [
                'creator_nickname' => $creatorNickname,
                'creator_username' => (string) ($creatorInfo['creator_username'] ?? ''),
                'creator_avatar_url' => (string) ($creatorInfo['creator_avatar_url'] ?? ''),
                'privacy_level_options' => $privacyLevels,
                'comment_disabled' => (bool) ($creatorInfo['comment_disabled'] ?? false),
                'duet_disabled' => (bool) ($creatorInfo['duet_disabled'] ?? false),
                'stitch_disabled' => (bool) ($creatorInfo['stitch_disabled'] ?? false),
                'max_video_post_duration_sec' => is_numeric($creatorInfo['max_video_post_duration_sec'] ?? null)
                    ? (int) $creatorInfo['max_video_post_duration_sec']
                    : null,
            ];

            $publishingCapabilities = $account->publishing_capabilities ?? [];
            $publishingCapabilities['tiktok'] = array_merge(
                $publishingCapabilities['tiktok'] ?? [],
                [
                    'can_direct_publish_video' => true,
                    'can_publish_video' => true,
                    'privacy_levels_supported' => $privacyLevels,
                    'max_video_duration' => $this->tiktokCreatorInfo['max_video_post_duration_sec'],
                    'delivery_mode' => 'direct',
                ]
            );
            $apiMetadata = $account->api_metadata ?? [];
            $apiMetadata['content_posting_info'] = $this->tiktokCreatorInfo;

            $account->update([
                'publishing_capabilities' => $publishingCapabilities,
                'api_metadata' => $apiMetadata,
                'last_api_check_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Impossibile preparare le opzioni TikTok Direct Post', [
                'account_id' => $account->id,
                'exception' => $exception::class,
            ]);
            $this->tiktokDirectOptionsError = 'Non è stato possibile aggiornare le opzioni dal profilo TikTok. Riprova prima di pubblicare.';
        }
    }

    private function buildTikTokDirectSnapshotOptions(ClientSocialAccount $account): array
    {
        if (
            config('services.tiktok.delivery_mode') !== 'direct'
            || ! config('services.tiktok.direct_publish_enabled', false)
        ) {
            throw ValidationException::withMessages([
                'tiktokDirectOptions' => 'La pubblicazione diretta TikTok non è configurata correttamente.',
            ]);
        }

        if ($this->tiktokDirectOptionsError !== null) {
            throw ValidationException::withMessages([
                'tiktokDirectOptions' => $this->tiktokDirectOptionsError,
            ]);
        }

        $account->refresh();
        $creatorInfo = $account->api_metadata['content_posting_info'] ?? [];
        $privacyLevels = array_values(array_filter(
            $account->publishing_capabilities['tiktok']['privacy_levels_supported'] ?? [],
            fn (mixed $level): bool => is_string($level)
                && in_array($level, self::TIKTOK_PRIVACY_LEVELS, true)
        ));

        if ($privacyLevels === [] || empty($creatorInfo['creator_nickname'])) {
            throw ValidationException::withMessages([
                'tiktokDirectOptions' => 'Aggiorna le opzioni del profilo TikTok prima di pubblicare.',
            ]);
        }

        $this->validate([
            'tiktokDirectOptions.privacy_level' => ['required', 'string', Rule::in($privacyLevels)],
            'tiktokDirectOptions.allow_comment' => ['boolean'],
            'tiktokDirectOptions.allow_duet' => ['boolean'],
            'tiktokDirectOptions.allow_stitch' => ['boolean'],
            'tiktokDirectOptions.commercial_content' => ['boolean'],
            'tiktokDirectOptions.brand_organic_toggle' => ['boolean'],
            'tiktokDirectOptions.brand_content_toggle' => ['boolean'],
            'tiktokDirectOptions.is_aigc' => ['boolean'],
            'tiktokDirectOptions.consent' => ['accepted'],
        ], [
            'tiktokDirectOptions.privacy_level.required' => 'Seleziona manualmente chi può vedere il contenuto su TikTok.',
            'tiktokDirectOptions.privacy_level.in' => 'La visibilità scelta non è disponibile per questo profilo TikTok.',
            'tiktokDirectOptions.consent.accepted' => 'Conferma le dichiarazioni TikTok prima di pubblicare.',
        ]);

        $commercialContent = (bool) $this->tiktokDirectOptions['commercial_content'];
        $brandOrganic = $commercialContent
            && (bool) $this->tiktokDirectOptions['brand_organic_toggle'];
        $brandContent = $commercialContent
            && (bool) $this->tiktokDirectOptions['brand_content_toggle'];

        if ($commercialContent && ! $brandOrganic && ! $brandContent) {
            throw ValidationException::withMessages([
                'tiktokDirectOptions.commercial_content' => 'Indica se il contenuto promuove il tuo brand, un soggetto terzo o entrambi.',
            ]);
        }

        if (
            $brandContent
            && ! in_array($this->tiktokDirectOptions['privacy_level'], [
                'PUBLIC_TO_EVERYONE',
                'MUTUAL_FOLLOW_FRIENDS',
            ], true)
        ) {
            throw ValidationException::withMessages([
                'tiktokDirectOptions.brand_content_toggle' => 'I contenuti sponsorizzati da terzi non possono usare questa visibilità.',
            ]);
        }

        $isVideoPost = $this->hasVideoMedia();
        $privacyOptions = [
            'privacy_level' => $this->tiktokDirectOptions['privacy_level'],
            'disable_comment' => (bool) ($creatorInfo['comment_disabled'] ?? false)
                || ! (bool) $this->tiktokDirectOptions['allow_comment'],
            'disable_duet' => ! $isVideoPost
                || (bool) ($creatorInfo['duet_disabled'] ?? false)
                || ! (bool) $this->tiktokDirectOptions['allow_duet'],
            'disable_stitch' => ! $isVideoPost
                || (bool) ($creatorInfo['stitch_disabled'] ?? false)
                || ! (bool) $this->tiktokDirectOptions['allow_stitch'],
        ];
        $platformOptions = [
            'delivery_mode' => 'direct',
            'commercial_content_disclosed' => $commercialContent,
            'brand_content_toggle' => $brandContent,
            'brand_organic_toggle' => $brandOrganic,
            'is_aigc' => (bool) $this->tiktokDirectOptions['is_aigc'],
            'creator_consent_confirmed' => true,
            'creator_consent_policy' => $brandContent
                ? 'branded_content_and_music_usage'
                : 'music_usage',
            'creator_nickname' => (string) $creatorInfo['creator_nickname'],
            'creator_info_checked_at' => $account->last_api_check_at?->toIso8601String(),
        ];

        return [$privacyOptions, $platformOptions];
    }

    public function publishToSocial(string $platform)
    {
        $this->authorize('update', $this->post);

        if (! in_array($this->post->status, [
            MarketingCampaignPostStatus::Approved,
            MarketingCampaignPostStatus::Failed,
        ])) {
            $this->addError('post', 'Stato del post non compatibile con la pubblicazione.');

            return;
        }

        try {
            // We no longer need the legacy preflight check here. The preflight is part of the execution action.
            // Also, we don't dispatch the legacy job. We synchronously create the publication via Create action, then dispatch Execute job.

            $platformEnum = SocialPlatform::tryFrom($platform);
            $account = $this->post->campaign->client->socialAccountFor($platform);

            if (! $platformEnum || ! $account) {
                $this->addError('post', 'L\'account social selezionato non è collegato correttamente.');

                return;
            }

            $version = $this->post->currentVersion;
            if (! $version) {
                $this->addError('post', 'Salva una versione del post prima di pubblicarlo.');

                return;
            }

            $privacyOptions = [];
            $platformOptions = [];

            if ($platformEnum === SocialPlatform::Tiktok) {
                $platformOptions['delivery_mode'] = (string) config('services.tiktok.delivery_mode', 'disabled');

                if ($platformOptions['delivery_mode'] === 'direct') {
                    [$privacyOptions, $platformOptions] = $this->buildTikTokDirectSnapshotOptions($account);
                }
            }

            $createAction = app(CreateMarketingCampaignPostPublicationAction::class);
            $publication = $createAction->execute(
                post: $this->post,
                version: $version,
                platform: $platformEnum,
                account: $account,
                privacyOptions: $privacyOptions,
                publicationType: 'publish',
                platformOptions: $platformOptions
            );

            // Dispatch async job
            ExecuteMarketingCampaignPostPublicationJob::dispatch($publication->id);

            if ($platformEnum === SocialPlatform::Tiktok && ($platformOptions['delivery_mode'] ?? null) === 'direct') {
                $this->tiktokDirectOptions['consent'] = false;
            }

            session()->flash("success_publish_{$platform}", "Pubblicazione su {$platform} avviata.");
            $this->refreshPost();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            Log::error('Errore durante dispatch pubblicazione social', ['error' => $e->getMessage()]);
            $this->addError('post', 'Non è stato possibile avviare la pubblicazione. Controlla il collegamento dell\'account e riprova.');
        }
    }

    public function retryPublication(int $publicationId)
    {
        $this->authorize('update', clone $this->post);

        $publication = $this->post->publications()->whereKey($publicationId)->first();
        if (! $publication) {
            return;
        }

        $isTikTokDirectRetry = $publication->platform === SocialPlatform::Tiktok
            && data_get($publication->payload_snapshot, 'platform_options.delivery_mode') === 'direct';

        if ($isTikTokDirectRetry) {
            if (config('services.tiktok.delivery_mode') !== 'direct') {
                $this->addError('post', 'Questo tentativo usa Direct Post, ma il server TikTok non è più in modalità diretta.');

                return;
            }

            $this->validate([
                'tiktokDirectOptions.consent' => ['accepted'],
            ], [
                'tiktokDirectOptions.consent.accepted' => 'Conferma le dichiarazioni TikTok prima di riprovare.',
            ]);
        }

        try {
            $retryAction = app(RetryMarketingCampaignPostPublicationAction::class);
            $newPublication = $retryAction->execute($publication);

            ExecuteMarketingCampaignPostPublicationJob::dispatch($newPublication->id);
            if ($isTikTokDirectRetry) {
                $this->tiktokDirectOptions['consent'] = false;
            }
            session()->flash("success_publish_{$publication->platform->value}", "Riavvio pubblicazione su {$publication->platform->value} in corso.");
            $this->refreshPost();
        } catch (\Exception $e) {
            Log::error('Unable to retry social publication', [
                'post_id' => $this->post->id,
                'publication_id' => $publicationId,
                'error' => $e->getMessage(),
            ]);
            $this->addError('post', 'Non è stato possibile riprovare la pubblicazione. Attendi qualche istante e riprova.');
        }
    }

    public function forceFailPublication(int $publicationId)
    {
        $this->authorize('update', clone $this->post);

        $publication = $this->post->publications()->whereKey($publicationId)->first();
        // forceFailPublication is redundant if already failed, but kept for compatibility
        if ($publication && $publication->status === PublicationStatus::Failed) {
            $publication->update(['status' => PublicationStatus::Failed->value]);

            app(SyncMarketingCampaignPostPublicationStatusAction::class)->execute($this->post);
            session()->flash("error_publish_{$publication->platform->value}", "Post marcato come definitivamente fallito su {$publication->platform->value}.");
            $this->refreshPost();
        }
    }

    public function deletePost()
    {
        $this->authorize('delete', $this->post);

        if (! $this->getCanDeletePostProperty()) {
            $this->addError(
                'post',
                'Questo post contiene versioni salvate o pubblicazioni social e non può essere eliminato. La rimozione dai social va gestita separatamente.'
            );

            return;
        }

        try {
            app(DeleteMarketingCampaignPostAction::class)->execute($this->post);
        } catch (HistoricalPostProtectedException $e) {
            Log::notice('Historical marketing post deletion blocked', [
                'post_id' => $this->post->id,
            ]);
            $this->addError(
                'post',
                'Questo post contiene versioni salvate o pubblicazioni social e non può essere eliminato. La rimozione dai social va gestita separatamente.'
            );

            return;
        } catch (\Exception $e) {
            Log::error('Unable to delete marketing post', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);
            $this->addError('post', 'Non è stato possibile eliminare il post. Riprova tra poco.');

            return;
        }

        return redirect()->route('marketing-campaigns.show', $this->campaign);
    }

    #[Computed]
    public function getCanDeletePostProperty(): bool
    {
        return ! $this->post->versions()->exists()
            && ! $this->post->publications()->exists();
    }

    private function prepareClientIdentity(&$newlyCreatedClientIdentityPaths)
    {
        $updates = [];

        if ($this->include_client_logo && $this->runtime_logo && ($this->save_runtime_logo_to_client || ! $this->form['ai_analysis_enabled'])) {
            if ($this->runtime_logo instanceof UploadedFile) {
                $filename = 'logo_'.Str::uuid()->toString().'.'.$this->runtime_logo->getClientOriginalExtension();
                $newLogoPath = $this->runtime_logo->storeAs('clients/logos', $filename, 'public');
                $newlyCreatedClientIdentityPaths[] = $newLogoPath;

                $updates['logo_path'] = $newLogoPath;
            }
        }

        if ($this->include_client_header && $this->runtime_activity_description && ($this->save_runtime_activity_to_client || ! $this->form['ai_analysis_enabled'])) {
            $updates['activity_description'] = $this->runtime_activity_description;
        }

        return $updates;
    }

    private function commitClientIdentityUpdates(bool $logoUpdated, bool $activityUpdated, ?string $oldLogoPath)
    {
        if ($logoUpdated) {
            $this->runtime_logo = null;
            $this->save_runtime_logo_to_client = false;
        }
        if ($activityUpdated) {
            $this->runtime_activity_description = null;
            $this->save_runtime_activity_to_client = false;
        }
        if ($oldLogoPath) {
            try {
                Storage::disk('public')->delete($oldLogoPath);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete old client logo', ['path' => $oldLogoPath, 'error' => $e->getMessage()]);
            }
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
            SocialPlatform::Instagram->value,
            SocialPlatform::Facebook->value,
            SocialPlatform::Tiktok->value,
        ];
        foreach ($platforms as $platform) {
            $account = $this->post->campaign->client->socialAccountFor($platform);
            if (! $account) {
                $this->preflightResults[$platform] = null;

                continue;
            }

            if (in_array($platform, [SocialPlatform::Facebook->value, SocialPlatform::Instagram->value])) {
                $this->preflightResults[$platform] = app(MetaPreflightService::class)->runPreflight($this->post, $account);
            } elseif ($platform === SocialPlatform::Tiktok->value) {
                $this->preflightResults[$platform] = app(TikTokPreflightService::class)->runPreflight($this->post, $account);
            }
        }
    }

    public function getPreflightResult(string $platform): ?PreflightResult
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

    public function failedLocalMediaUpload(array $uids): void
    {
        $failedUids = array_fill_keys(
            array_values(array_filter($uids, fn ($uid) => is_string($uid))),
            true
        );

        $this->selected_media_items = array_values(array_filter(
            $this->selected_media_items,
            fn ($item) => ! isset($failedUids[$item['uid'] ?? ''])
        ));
        $this->media = [];
        $this->syncLegacyPropertiesFromUnified();
        $this->addError(
            'media',
            'Caricamento non riuscito. Controlla formato e dimensione del file e riprova.'
        );
    }

    public function updatedMedia()
    {
        if (! is_array($this->media)) {
            return;
        }

        $this->validate([
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => MarketingCampaignPostMediaUploadPolicy::validationRules(),
        ]);

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

            $isVid = Str::startsWith($uploadedFile->getMimeType(), 'video/');
            $this->selected_media_items[] = [
                'uid' => 'local:'.uniqid(),
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
                        'id' => $item['existing_id'],
                        'type' => $item['type'],
                        'mime_type' => $item['mime_type'] ?? null,
                        'source' => 'existing',
                        'url' => $item['preview_url'],
                    ];
                }

                if ($item['source'] === 'local') {
                    $m = $this->all_local_media[$item['local_index']] ?? null;
                    if (! $m) {
                        return null;
                    }
                    $isVid = $item['type'] === 'video';
                    $url = method_exists($m, 'temporaryUrl') ? ($isVid ? $this->temporaryVideoPreviewUrl($m).'#t=0.001' : $m->temporaryUrl()) : '';

                    return $url ? ['uid' => $item['uid'], 'type' => $item['type'], 'url' => $url, 'source' => 'local'] : null;
                }

                if ($item['source'] === 'nextcloud') {
                    $isVid = $item['type'] === 'video';

                    return [
                        'uid' => $item['uid'],
                        'type' => $item['type'],
                        'source' => 'nextcloud',
                        'url' => $isVid
                            ? route('nextcloud.download', ['path' => $item['nextcloud_path']]).'#t=0.001'
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

    public function temporaryVideoPreviewUrl(TemporaryUploadedFile $file): string
    {
        return URL::signedRoute('social.temporary-video-preview', [
            'filename' => $file->getFilename(),
        ], now()->addMinutes(30));
    }
}
