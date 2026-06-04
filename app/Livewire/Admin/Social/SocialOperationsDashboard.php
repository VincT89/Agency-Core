<?php

namespace App\Livewire\Admin\Social;

use App\Domain\Social\Actions\PublishMarketingCampaignPostAction;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

class SocialOperationsDashboard extends Component
{
    use WithPagination;

    public string $filter = 'all'; // all, needs_manual_review, failed, stale_publishing

    public function mount()
    {
        $this->authorize('manage_social_operations');
    }

    public function updatedFilter()
    {
        $this->resetPage();
    }

    public function retryPublication(int $publicationId, PublishMarketingCampaignPostAction $publishAction, SyncMarketingCampaignPostPublicationStatusAction $syncAction)
    {
        $this->authorize('manage_social_operations');

        $publication = MarketingCampaignPostPublication::findOrFail($publicationId);

        if (in_array($publication->status, [
            PublicationStatus::Published,
            PublicationStatus::Cancelled
        ])) {
            session()->flash('error', 'Impossibile riprovare: la pubblicazione è in uno stato terminale o di successo.');
            return;
        }

        // Se era failed IG e stiamo rifacendo da zero, dismettiamo questo e non usiamo il vecchio container
        if ($publication->platform === \App\Enums\Social\SocialPlatform::Instagram && $publication->status === PublicationStatus::Failed) {
            $publication->update([
                'status' => PublicationStatus::Cancelled->value,
                'error_message' => 'Dismesso (sostituito da nuovo tentativo)',
            ]);
            
            // Creiamo un nuovo correlation id
            $correlationId = Str::uuid()->toString();
            
            try {
                $publishAction->execute($publication->post, 'instagram', $correlationId);
                session()->flash('success', 'Nuova pubblicazione Instagram avviata con successo. Il vecchio container è stato scartato.');
            } catch (\Exception $e) {
                Log::error('Errore durante il retry da zero IG', ['error' => $e->getMessage()]);
                session()->flash('error', 'Errore durante l\'avvio della nuova pubblicazione: ' . $e->getMessage());
            }

        } else {
            // Per FB o retry classici, possiamo rifare
            try {
                $correlationId = Str::uuid()->toString();
                $publishAction->execute($publication->post, $publication->platform->value, $correlationId);
                session()->flash('success', 'Pubblicazione riavviata con successo.');
            } catch (\Exception $e) {
                session()->flash('error', 'Errore durante il retry: ' . $e->getMessage());
            }
        }
        
        $syncAction->execute($publication->post);
    }

    public function refreshPublication(int $publicationId, \App\Domain\Social\Actions\RefreshPublicationStatusAction $action, SyncMarketingCampaignPostPublicationStatusAction $syncAction)
    {
        $this->authorize('manage_social_operations');

        $publication = MarketingCampaignPostPublication::findOrFail($publicationId);

        if (in_array($publication->status, [
            PublicationStatus::Published, 
            PublicationStatus::Failed,
            PublicationStatus::Cancelled
        ])) {
            session()->flash('error', 'La pubblicazione è già in uno stato terminale.');
            return;
        }

        try {
            $action->execute($publication);
            
            if ($publication->platform === \App\Enums\Social\SocialPlatform::Tiktok) {
                session()->flash('success', 'Aggiornamento accodato.');
            } else {
                if ($publication->post) {
                    $syncAction->execute($publication->post);
                }
                session()->flash('success', 'Stato pubblicazione verificato.');
            }
        } catch (\App\Exceptions\Social\ContainerProcessingException $e) {
            session()->flash('info', 'Pubblicazione ancora in progress: ' . $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Errore imprevisto durante il controllo: ' . $e->getMessage());
        }
    }

    public function forceFailPublication(int $publicationId, SyncMarketingCampaignPostPublicationStatusAction $syncAction)
    {
        $this->authorize('manage_social_operations');

        $publication = MarketingCampaignPostPublication::findOrFail($publicationId);

        if (in_array($publication->status, [
            PublicationStatus::Published,
            PublicationStatus::Cancelled
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

    public function render()
    {
        $query = MarketingCampaignPostPublication::with([
            'post.campaign.client', 
            'socialAccount.agencyAsset.connection'
        ])
        ->orderBy('updated_at', 'desc');

        if ($this->filter === 'needs_manual_review') {
            $query->where('status', PublicationStatus::Failed->value); // Mappato su failed per compatibilità
        } elseif ($this->filter === 'failed') {
            $query->where('status', PublicationStatus::Failed->value);
        } elseif ($this->filter === 'stale_publishing') {
            $maxLifecycle = config('services.meta.instagram.max_container_lifecycle', 15);
            $query->whereIn('status', [PublicationStatus::Publishing->value, PublicationStatus::Pending->value])
                  ->where('updated_at', '<', now()->subMinutes($maxLifecycle));
        } else {
            // "all" mostra le 3 categorie sopra combinate
            $maxLifecycle = config('services.meta.instagram.max_container_lifecycle', 15);
            
            $query->where(function($q) use ($maxLifecycle) {
                $q->where('status', PublicationStatus::Failed->value)
                  ->orWhere(function($staleQ) use ($maxLifecycle) {
                      $staleQ->whereIn('status', [PublicationStatus::Publishing->value, PublicationStatus::Pending->value])
                             ->where('updated_at', '<', now()->subMinutes($maxLifecycle));
                  });
            });
        }

        $publications = $query->paginate(20);

        return view('livewire.admin.social.social-operations-dashboard', [
            'publications' => $publications
        ])->layout('layouts.app', ['title' => 'Social Operations']);
    }
}
