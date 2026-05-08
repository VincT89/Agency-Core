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

    public $media; // Uploaded file
    public $existing_media_url = null; // Preview of existing file

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

    public function mount(MarketingCampaign $campaign, MarketingCampaignPost $post)
    {
        $this->authorize('view', $post);
        $this->campaign = $campaign;

        // Eager load related data
        $post->load(['currentVersion', 'comments.user']);
        $this->post = $post;

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
            'publishing_platforms' => $post->publishing_platforms ?? [],
        ];
        $this->existing_media_url = $post->preview_url;
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
        $this->existing_media_url = $this->post->preview_url;
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
    }

    private function nextcloudFilesOnlyImages(): array
    {
        return collect($this->nextcloud_files)
            ->filter(fn ($file) => empty($file['is_dir']) && ($file['is_image'] ?? false))
            ->values()
            ->all();
    }

    public function savePost()
    {
        $this->validate();

        if ($this->post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile modificare un post già pubblicato.');
            return;
        }

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;

        $oldMediaPath = null;
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

                if ($this->post->media_path) {
                    $oldMediaPath = $this->post->media_path;
                }
            }
        } elseif ($data['media_source'] === 'nextcloud') {
            $data['media_path'] = null;
            if ($this->post->media_source === 'local' && $this->post->media_path) {
                $oldMediaPath = $this->post->media_path;
            }
            if (empty($data['nextcloud_path'])) {
                $this->addError('form.nextcloud_path', 'Seleziona un file da Nextcloud.');
                return;
            }
            if (!$this->prepareNextcloudMedia($data)) {
                return;
            }
        }

        $this->authorize('update', $this->post);
        $this->post->update($data);

        if ($oldMediaPath) {
            Storage::disk('public')->delete($oldMediaPath);
        }

        $this->dispatch('post-saved');
        $this->refreshPost();
    }

    public function saveAndSubmitToN8n(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction $submitAction)
    {
        $this->validate();

        if ($this->post->status === MarketingCampaignPostStatus::Published) {
            $this->addError('post', 'Impossibile inviare a N8n un post già pubblicato.');
            return;
        }

        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;
        $data['status'] = MarketingCampaignPostStatus::PendingN8n->value;

        $oldMediaPath = null;
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

                if ($this->post->media_path) {
                    $oldMediaPath = $this->post->media_path;
                }
            }
        } elseif ($data['media_source'] === 'nextcloud') {
            $data['media_path'] = null;
            if ($this->post->media_source === 'local' && $this->post->media_path) {
                $oldMediaPath = $this->post->media_path;
            }
            if (empty($data['nextcloud_path'])) {
                $this->addError('form.nextcloud_path', 'Seleziona un file da Nextcloud.');
                return;
            }
            if (!$this->prepareNextcloudMedia($data)) {
                return;
            }
        }

        $this->authorize('update', $this->post);
        $this->post->update($data);

        if ($oldMediaPath) {
            Storage::disk('public')->delete($oldMediaPath);
        }

        $runtimeClientData = [
            'include_client_logo' => $this->include_client_logo,
            'include_client_header' => $this->include_client_header,
            'runtime_logo' => $this->runtime_logo,
            'runtime_activity_description' => $this->runtime_activity_description,
            'save_runtime_logo_to_client' => $this->save_runtime_logo_to_client,
            'save_runtime_activity_to_client' => $this->save_runtime_activity_to_client,
        ];

        $submitAction->execute($this->post, $runtimeClientData);

        $this->dispatch('post-submitted-n8n');
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
            $action->execute($this->post, auth()->user(), $type);
            $this->dispatch('post-regenerating');
        } catch (\Exception $e) {
            $this->addError('post', $e->getMessage());
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

        if ($this->post->media_path) {
            Storage::disk('public')->delete($this->post->media_path);
        }

        $this->post->delete();

        return redirect()->route('marketing-campaigns.show', $this->campaign);
    }

    private function prepareNextcloudMedia(array &$data): bool
    {
        if (empty($data['nextcloud_path'])) {
            return true;
        }

        if (!$this->selected_nextcloud_file) {
            if ($this->post && $this->post->nextcloud_path === $data['nextcloud_path']) {
                $data['nextcloud_share_url'] = $this->post->nextcloud_share_url;
                $data['nextcloud_file_id'] = $this->post->nextcloud_file_id;
                $data['media_original_name'] = $this->post->media_original_name;
                $data['media_mime'] = $this->post->media_mime;
                return true;
            }
            $this->addError('form.nextcloud_path', 'Dati del file mancanti. Seleziona nuovamente il file da Nextcloud.');
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
        return view('livewire.social.marketing-campaigns.marketing-campaign-post-show')
            ->layout('layouts.app');
    }
}
