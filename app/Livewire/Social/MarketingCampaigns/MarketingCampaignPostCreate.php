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

    public $media; // Uploaded file
    
    // Nextcloud State
    public $nextcloud_media_kind = 'photo';
    public $nextcloud_browse_path = '/';
    public $nextcloud_files = [];
    public ?array $selected_nextcloud_file = null;
    public ?array $pending_nextcloud_file = null;
    public ?array $preview_nextcloud_file = null;
    public bool $showNextcloudPicker = false;
    public ?string $nextcloud_error = null;

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
            'media' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,mp4,mov,webm,m4v',
                'max:51200',
            ],
            'runtime_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'runtime_activity_description' => 'nullable|string|max:1000',
        ];
    }

    public function mount(MarketingCampaign $campaign)
    {
        $this->authorize('update', $campaign);
        $this->campaign = $campaign;
    }

    public function updatedFormAiAnalysisEnabled($value)
    {
        if (!$value) {
            $this->include_client_logo = true;
            $this->include_client_header = true;
            $this->runtime_logo = null;
            $this->runtime_activity_description = null;
            $this->save_runtime_logo_to_client = false;
            $this->save_runtime_activity_to_client = false;
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
        $this->pending_nextcloud_file = $this->selected_nextcloud_file;

        $service = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
        $this->browseNextcloud($service->mediaRoot($mediaKind));
    }

    public function closeNextcloudPicker(): void
    {
        $this->showNextcloudPicker = false;
        $this->pending_nextcloud_file = null;
    }

    public function selectNextcloudFile($path, $name, $size, $mime = null, $fileId = null): void
    {
        $this->pending_nextcloud_file = [
            'path' => $path,
            'name' => $name,
            'size' => $size,
            'mime' => $mime,
            'file_id' => $fileId,
        ];
    }

    public function confirmNextcloudSelection(): void
    {
        if (!$this->pending_nextcloud_file) {
            $this->addError('form.nextcloud_path', 'Seleziona una foto da Nextcloud.');
            return;
        }

        $this->selected_nextcloud_file = $this->pending_nextcloud_file;
        $this->form['nextcloud_path'] = $this->selected_nextcloud_file['path'];
        $this->form['media_source'] = 'nextcloud';

        $this->showNextcloudPicker = false;
        $this->pending_nextcloud_file = null;
    }

    public function removeNextcloudFile(): void
    {
        $this->selected_nextcloud_file = null;
        $this->pending_nextcloud_file = null;
        $this->preview_nextcloud_file = null;
        $this->form['nextcloud_path'] = null;
    }

    public function openNextcloudPreview(string $path): void
    {
        $file = collect($this->nextcloudFilesOnlyImages())
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
        $this->pending_nextcloud_file = $files[$nextIndex];
    }

    private function nextcloudFilesOnlyImages(): array
    {
        return collect($this->nextcloud_files)
            ->filter(fn ($file) => empty($file['is_dir']) && ($file['is_image'] ?? false))
            ->values()
            ->all();
    }

    public function save()
    {
        $this->validate();

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;
        $data['created_by'] = auth()->id();

        if ($data['media_source'] === 'local') {
            $data['nextcloud_path'] = null;
            $data['nextcloud_share_url'] = null;
            $data['nextcloud_file_id'] = null;
            
            if ($this->media) {
                $filename = Str::slug(pathinfo($this->media->getClientOriginalName(), PATHINFO_FILENAME)) 
                            . '_' . time() . '.' . $this->media->getClientOriginalExtension();
                $path = $this->media->storeAs('marketing/campaign-posts', $filename, 'public');

                $data['media_path'] = $path;
                $data['media_original_name'] = $this->media->getClientOriginalName();
                $data['media_mime'] = $this->media->getMimeType();
            }
        } elseif ($data['media_source'] === 'nextcloud') {
            $data['media_path'] = null;
            if (empty($data['nextcloud_path'])) {
                $this->addError('form.nextcloud_path', 'Seleziona un file da Nextcloud.');
                return;
            }
            if (!$this->prepareNextcloudMedia($data)) {
                return;
            }
        }

        $post = MarketingCampaignPost::create($data);

        return redirect()->route('marketing-campaigns.posts.show', [
            'campaign' => $this->campaign->id,
            'post' => $post->id
        ]);
    }

    public function saveAndSubmitToN8n(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction $submitAction)
    {
        $this->validate();
        
        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;
        $data['status'] = MarketingCampaignPostStatus::PendingN8n->value;
        $data['created_by'] = auth()->id();

        if ($data['media_source'] === 'local') {
            $data['nextcloud_path'] = null;
            $data['nextcloud_share_url'] = null;
            $data['nextcloud_file_id'] = null;
            
            if ($this->media) {
                $filename = Str::slug(pathinfo($this->media->getClientOriginalName(), PATHINFO_FILENAME)) 
                            . '_' . time() . '.' . $this->media->getClientOriginalExtension();
                $path = $this->media->storeAs('marketing/campaign-posts', $filename, 'public');

                $data['media_path'] = $path;
                $data['media_original_name'] = $this->media->getClientOriginalName();
                $data['media_mime'] = $this->media->getMimeType();
            }
        } elseif ($data['media_source'] === 'nextcloud') {
            $data['media_path'] = null;
            if (empty($data['nextcloud_path'])) {
                $this->addError('form.nextcloud_path', 'Seleziona un file da Nextcloud.');
                return;
            }
            if (!$this->prepareNextcloudMedia($data)) {
                return;
            }
        }

        $post = MarketingCampaignPost::create($data);

        $runtimeClientData = [
            'include_client_logo' => $this->include_client_logo,
            'include_client_header' => $this->include_client_header,
            'runtime_logo' => $this->runtime_logo,
            'runtime_activity_description' => $this->runtime_activity_description,
            'save_runtime_logo_to_client' => $this->save_runtime_logo_to_client,
            'save_runtime_activity_to_client' => $this->save_runtime_activity_to_client,
        ];

        $submitAction->execute($post, $runtimeClientData);

        return redirect()->route('marketing-campaigns.posts.show', [
            'campaign' => $this->campaign->id,
            'post' => $post->id
        ]);
    }

    private function prepareNextcloudMedia(array &$data): bool
    {
        if (empty($data['nextcloud_path'])) {
            return true;
        }

        if (!$this->selected_nextcloud_file) {
            $this->addError('form.nextcloud_path', 'Seleziona un file da Nextcloud.');
            return false;
        }

        $service = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
        $shareUrl = $service->createPublicShare($data['nextcloud_path']);

        if (!$shareUrl) {
            $this->addError('form.nextcloud_path', 'Impossibile creare il link pubblico Nextcloud.');
            return false;
        }

        $data['nextcloud_share_url'] = $shareUrl;
        $data['nextcloud_file_id'] = $this->selected_nextcloud_file['file_id'] ?? null;
        $data['media_path'] = null;
        $data['media_original_name'] = $this->selected_nextcloud_file['name'] ?? basename($data['nextcloud_path']);
        $data['media_mime'] = $this->selected_nextcloud_file['mime'] ?? null;

        return true;
    }

    public function render()
    {
        return view('livewire.social.marketing-campaigns.marketing-campaign-post-create')
            ->layout('layouts.app');
    }
}
