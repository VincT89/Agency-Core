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

    public $media = []; // Uploaded files
    
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
                'image',
                'mimes:jpg,jpeg,png,webp',
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

        $this->selected_nextcloud_files = $this->pending_nextcloud_files;
        $this->selected_nextcloud_file = $this->selected_nextcloud_files[0]; // legacy fallback

        $this->form['nextcloud_path'] = $this->selected_nextcloud_file['path'];
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

    public function reorderLocalMedia($fromIndex, $toIndex): void
    {
        if (!isset($this->media[$fromIndex]) || $toIndex < 0 || $toIndex >= count($this->media)) return;
        
        $item = array_splice($this->media, $fromIndex, 1)[0];
        array_splice($this->media, $toIndex, 0, [$item]);
    }

    public function reorderNextcloudMedia($fromIndex, $toIndex): void
    {
        if (!isset($this->selected_nextcloud_files[$fromIndex]) || $toIndex < 0 || $toIndex >= count($this->selected_nextcloud_files)) return;

        $item = array_splice($this->selected_nextcloud_files, $fromIndex, 1)[0];
        array_splice($this->selected_nextcloud_files, $toIndex, 0, [$item]);
        
        // Aggiorna i fallback legacy sulla nuova prima immagine
        if (!empty($this->selected_nextcloud_files)) {
            $this->selected_nextcloud_file = $this->selected_nextcloud_files[0];
            $this->form['nextcloud_path'] = $this->selected_nextcloud_file['path'];
        }
    }

    public function save()
    {
        $this->validate();


        $data = $this->form;
        $data['marketing_campaign_id'] = $this->campaign->id;
        $data['created_by'] = auth()->id();

        $storedMedia = [];

        if (!$this->buildPostDataAndStoredMedia($data, $storedMedia)) {
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

        if (! $this->form['ai_analysis_enabled']) {
            app(\App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction::class)
                ->execute($post, auth()->user());
        }

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

        $storedMedia = [];

        if (!$this->buildPostDataAndStoredMedia($data, $storedMedia)) {
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
        ];

        $submitAction->execute($post, $runtimeClientData);

        return redirect()->route('marketing-campaigns.posts.show', [
            'campaign' => $this->campaign->id,
            'post' => $post->id
        ]);
    }

    private function buildPostDataAndStoredMedia(array &$data, array &$storedMedia): bool
    {
        // NOTA ARCHITETTURALE: Attualmente l'UI permette una sola sorgente alla volta 
        // tramite radio button (local o nextcloud). Non è previsto un ordinamento 
        // incrociato (merge-sort) tra le sorgenti. Se in futuro l'UI lo permetterà,
        // questa logica andrà unificata.
        
        if ($data['media_source'] === 'local') {
            $data['nextcloud_path'] = null;
            $data['nextcloud_share_url'] = null;
            $data['nextcloud_file_id'] = null;
            
            if (!empty($this->media)) {
                foreach ($this->media as $index => $uploadedFile) {
                    $filename = Str::slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME)) 
                                . '_' . time() . '_' . $index . '.' . $uploadedFile->getClientOriginalExtension();
                    $path = $uploadedFile->storeAs('marketing/campaign-posts', $filename, 'public');

                    $storedMedia[] = [
                        'source' => 'local',
                        'media_type' => 'image',
                        'disk' => 'public',
                        'path' => $path,
                        'mime_type' => $uploadedFile->getMimeType(),
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'sort_order' => $index,
                    ];
                }

                $data['media_path'] = $storedMedia[0]['path'];
                $data['media_original_name'] = $storedMedia[0]['original_name'];
                $data['media_mime'] = $storedMedia[0]['mime_type'];
            }
        } elseif ($data['media_source'] === 'nextcloud') {
            $data['media_path'] = null;
            if (empty($this->selected_nextcloud_files)) {
                $this->addError('form.nextcloud_path', 'Seleziona almeno un file da Nextcloud.');
                return false;
            }
            if (!$this->prepareNextcloudMedia($data, $storedMedia)) {
                return false;
            }
        }
        return true;
    }

    private function prepareNextcloudMedia(array &$data, array &$storedMedia): bool
    {
        if (empty($this->selected_nextcloud_files)) {
            $this->addError('form.nextcloud_path', 'Nessun file Nextcloud selezionato.');
            return false;
        }

        $service = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
        
        $baseSortOrder = count($storedMedia);
        foreach ($this->selected_nextcloud_files as $index => $ncFile) {
            $shareUrl = $service->createPublicShare($ncFile['path']);
            if (!$shareUrl) {
                $this->addError('form.nextcloud_path', "Impossibile creare il link pubblico Nextcloud per {$ncFile['name']}.");
                return false;
            }

            $storedMedia[] = [
                'source' => 'nextcloud',
                'media_type' => 'image',
                'disk' => null,
                'path' => null,
                'mime_type' => $ncFile['mime'] ?? null,
                'original_name' => $ncFile['name'] ?? basename($ncFile['path']),
                'nextcloud_path' => $ncFile['path'],
                'nextcloud_share_url' => $shareUrl,
                'nextcloud_file_id' => $ncFile['file_id'] ?? null,
                'sort_order' => $baseSortOrder + $index,
            ];
        }

        // Popola legacy con il primo file Nextcloud
        $firstNextcloudMedia = $storedMedia[$baseSortOrder] ?? null;
        if ($firstNextcloudMedia) {
            $data['nextcloud_path'] = $firstNextcloudMedia['nextcloud_path'];
            $data['nextcloud_share_url'] = $firstNextcloudMedia['nextcloud_share_url'];
            $data['nextcloud_file_id'] = $firstNextcloudMedia['nextcloud_file_id'];
            $data['media_path'] = null;
            $data['media_original_name'] = $firstNextcloudMedia['original_name'];
            $data['media_mime'] = $firstNextcloudMedia['mime_type'];
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
}
