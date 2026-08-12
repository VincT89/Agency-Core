<?php

namespace App\Livewire\Admin\Social;

use App\Domain\Social\Actions\ArchiveMarketingCampaignPostAction;
use App\Domain\Social\Actions\RefreshPublicationStatusAction;
use App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction;
use App\Domain\Social\Actions\RestoreMarketingCampaignPostAction;
use App\Domain\Social\Exceptions\MarketingCampaignPostArchiveException;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Exceptions\Social\ContainerProcessingException;
use App\Jobs\Social\ExecuteMarketingCampaignPostPublicationJob;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class SocialOperationsDashboard extends Component
{
    use WithPagination;

    public string $filter = 'all'; // all, active, published, needs_manual_review, failed, stale_publishing, attempt_history, archived

    public function mount()
    {
        $this->authorize('manage_social_operations');
    }

    public function updatedFilter()
    {
        $this->resetPage();
    }

    public function retryPublication(int $publicationId, SyncMarketingCampaignPostPublicationStatusAction $syncAction)
    {
        $this->authorize('manage_social_operations');

        $publication = MarketingCampaignPostPublication::findOrFail($publicationId);

        if ($publication->post?->isArchived()) {
            session()->flash('error', 'Ripristina il post prima di avviare un nuovo tentativo.');

            return;
        }

        try {
            $retryAction = app(RetryMarketingCampaignPostPublicationAction::class);
            $newPublication = $retryAction->execute($publication);

            ExecuteMarketingCampaignPostPublicationJob::dispatch($newPublication->id);
            session()->flash('success', 'Nuova pubblicazione avviata con successo. Il vecchio record è stato riprovato.');
        } catch (\Exception $e) {
            Log::error('Errore durante il retry da dashboard', ['error' => $e->getMessage()]);
            session()->flash('error', 'Non è stato possibile avviare un nuovo tentativo di pubblicazione.');
        }

        $syncAction->execute($publication->post);
    }

    public function refreshPublication(int $publicationId, RefreshPublicationStatusAction $action, SyncMarketingCampaignPostPublicationStatusAction $syncAction)
    {
        $this->authorize('manage_social_operations');

        $publication = MarketingCampaignPostPublication::findOrFail($publicationId);

        if ($publication->post?->isArchived()) {
            session()->flash('error', 'Ripristina il post prima di aggiornarne la pubblicazione.');

            return;
        }

        if (in_array($publication->status, [
            PublicationStatus::Published,
            PublicationStatus::Failed,
            PublicationStatus::Cancelled,
            PublicationStatus::Superseded,
            PublicationStatus::Abandoned,
        ])) {
            session()->flash('error', 'La pubblicazione è già in uno stato terminale.');

            return;
        }

        try {
            $action->execute($publication);

            if ($publication->platform === SocialPlatform::Tiktok) {
                session()->flash('success', 'Aggiornamento accodato.');
            } else {
                if ($publication->post) {
                    $syncAction->execute($publication->post);
                }
                session()->flash('success', 'Stato pubblicazione verificato.');
            }
        } catch (ContainerProcessingException $e) {
            Log::info('Social publication is still being processed', [
                'publication_id' => $publicationId,
                'message' => $e->getMessage(),
            ]);
            session()->flash('info', 'La pubblicazione è ancora in corso. Controlla di nuovo tra poco.');
        } catch (\Exception $e) {
            Log::error('Unable to refresh social publication status', [
                'publication_id' => $publicationId,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Non è stato possibile aggiornare lo stato della pubblicazione.');
        }
    }

    public function forceFailPublication(int $publicationId, SyncMarketingCampaignPostPublicationStatusAction $syncAction)
    {
        $this->authorize('manage_social_operations');

        $publication = MarketingCampaignPostPublication::findOrFail($publicationId);

        if ($publication->post?->isArchived()) {
            session()->flash('error', 'Ripristina il post prima di modificarne lo stato.');

            return;
        }

        if (in_array($publication->status, [
            PublicationStatus::Published,
            PublicationStatus::Cancelled,
            PublicationStatus::Superseded,
            PublicationStatus::Abandoned,
            PublicationStatus::Failed,
        ])) {
            session()->flash('error', 'Impossibile forzare il fallimento: la pubblicazione è in uno stato terminale o di successo.');

            return;
        }

        $publication->update([
            'status' => PublicationStatus::Failed->value,
            'error_message' => 'Forzato a Fallito dall\'operatore.',
        ]);

        $syncAction->execute($publication->post);

        session()->flash('success', 'Pubblicazione marcata come Fallita definitivamente.');
    }

    public function archivePost(int $publicationId, ArchiveMarketingCampaignPostAction $action): void
    {
        $this->authorize('manage_social_operations');

        $publication = MarketingCampaignPostPublication::query()
            ->with('post.publications')
            ->findOrFail($publicationId);
        $post = $publication->post;

        if (! $post) {
            session()->flash('error', 'Il post collegato non è disponibile.');

            return;
        }

        $this->authorize('update', $post);

        try {
            $action->execute($post, auth()->user());
            session()->flash('success', 'Post archiviato localmente. Nessun contenuto è stato eliminato dai social.');
            $this->resetPage();
        } catch (MarketingCampaignPostArchiveException $exception) {
            session()->flash('error', 'Il post non può essere archiviato perché è pubblicato, parzialmente pubblicato o ancora in elaborazione.');
        }
    }

    public function restorePost(int $publicationId, RestoreMarketingCampaignPostAction $action): void
    {
        $this->authorize('manage_social_operations');

        $publication = MarketingCampaignPostPublication::query()
            ->with('post')
            ->findOrFail($publicationId);
        $post = $publication->post;

        if (! $post || ! $post->isArchived()) {
            session()->flash('error', 'Il post non risulta archiviato.');

            return;
        }

        $this->authorize('update', $post);
        $action->execute($post);
        session()->flash('success', 'Post ripristinato nelle viste operative.');
        $this->resetPage();
    }

    public function render()
    {
        $query = MarketingCampaignPostPublication::with([
            'post.campaign.client',
            'post.publications',
            'socialAccount.agencyAsset.connection',
        ])
            ->orderBy('updated_at', 'desc');

        if ($this->filter === 'archived') {
            $query->whereHas('post', fn ($postQuery) => $postQuery->onlyArchived());
        } else {
            $query->whereHas('post', fn ($postQuery) => $postQuery->notArchived());
        }

        if ($this->filter === 'active') {
            $query->whereIn('status', [
                PublicationStatus::Pending->value,
                PublicationStatus::Publishing->value,
            ]);
        } elseif ($this->filter === 'published') {
            $query->where('status', PublicationStatus::Published->value);
        } elseif ($this->filter === 'needs_manual_review') {
            $query->where('status', PublicationStatus::NeedsManualReview->value);
        } elseif ($this->filter === 'failed') {
            $query->where('status', PublicationStatus::Failed->value);
        } elseif ($this->filter === 'stale_publishing') {
            $maxLifecycle = config('services.meta.instagram.max_container_lifecycle', 15);
            $query->whereIn('status', [PublicationStatus::Publishing->value, PublicationStatus::Pending->value])
                ->where('updated_at', '<', now()->subMinutes($maxLifecycle));
        } elseif ($this->filter === 'attempt_history') {
            $query->whereIn('status', [
                PublicationStatus::Superseded->value,
                PublicationStatus::Abandoned->value,
            ]);
        } elseif ($this->filter === 'all') {
            $query->whereNotIn('status', [
                PublicationStatus::Superseded->value,
                PublicationStatus::Abandoned->value,
            ]);
        }

        $publications = $query->paginate(20);

        return view('livewire.admin.social.social-operations-dashboard', [
            'publications' => $publications,
        ])->layout('layouts.app', ['title' => 'Coda Social']);
    }
}
