<?php

namespace App\Livewire\Public;

use App\Domain\Social\Exceptions\MarketingCampaignPostMediaDeliveryException;
use App\Domain\Social\Exceptions\MarketingCampaignPostMediaResolutionException;
use App\Domain\Social\Services\MarketingCampaignPostMediaUrlResolver;
use App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver;
use App\Enums\Social\MarketingCampaignPostCommentType;
use App\Enums\Social\MarketingCampaignPostCommentVisibility;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Exceptions\Social\ClientReviewStateConflictException;
use App\Exceptions\Social\ClientReviewTokenExpiredException;
use App\Exceptions\Social\ClientReviewTokenUsedException;
use App\Exceptions\Social\ClientReviewVersionConflictException;
use App\Models\ClientReviewToken;
use App\Models\MarketingCampaignPost;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

class MarketingCampaignPostReview extends Component
{
    public ClientReviewToken $tokenRecord;

    public MarketingCampaignPost $post;

    public $clientName = '';

    public $clientEmail = '';

    public $commentBody = '';

    public string $successMessage = '';

    public bool $mediaResolutionFailed = false;

    public array $resolvedReviewMedia = [];

    private MarketingCampaignPostVersionMediaResolver $mediaResolver;

    private MarketingCampaignPostMediaUrlResolver $urlResolver;

    public function boot(
        MarketingCampaignPostVersionMediaResolver $mediaResolver,
        MarketingCampaignPostMediaUrlResolver $urlResolver
    ): void {
        $this->mediaResolver = $mediaResolver;
        $this->urlResolver = $urlResolver;
    }

    public function mount(string $token)
    {
        $this->tokenRecord = ClientReviewToken::where('token', $token)
            ->where('reviewable_type', MarketingCampaignPost::class)
            ->firstOrFail();

        if ($this->tokenRecord->isExpired() || $this->tokenRecord->isUsed()) {
            abort(403, 'Questo link di revisione è scaduto o è già stato utilizzato.');
        }

        $this->post = $this->tokenRecord->reviewable;

        if ($this->tokenRecord->marketing_campaign_post_version_id &&
            $this->tokenRecord->marketing_campaign_post_version_id !== $this->post->current_version_id) {
            abort(403, 'Questo link di revisione appartiene a una versione obsoleta del post. Richiedi un nuovo link al team.');
        }

        $client = $this->post->campaign->client;
        $this->clientName = $client->name;
        $this->clientEmail = $client->email;

        try {
            $resolution = $this->mediaResolver->resolveForPost($this->post);
            $this->resolvedReviewMedia = $this->urlResolver->orderedDeliveryUrls($resolution->mediaItems);
        } catch (MarketingCampaignPostMediaResolutionException|MarketingCampaignPostMediaDeliveryException $e) {
            $this->mediaResolutionFailed = true;
            Log::error('social.public_review.media_resolution_failed', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function approve()
    {
        if ($this->mediaResolutionFailed) {
            abort(409, 'Impossibile caricare in modo sicuro i media di questa versione. Contatta il team per ricevere un nuovo link.');
        }

        try {
            DB::transaction(function () {
                $token = ClientReviewToken::query()
                    ->whereKey($this->tokenRecord->id)
                    ->where('reviewable_type', MarketingCampaignPost::class)
                    ->lockForUpdate()
                    ->firstOrFail();
                $post = MarketingCampaignPost::query()
                    ->with('campaign.client')
                    ->lockForUpdate()
                    ->findOrFail($token->reviewable_id);

                if ($token->isUsed()) {
                    throw new ClientReviewTokenUsedException;
                }

                if ($token->isExpired()) {
                    throw new ClientReviewTokenExpiredException;
                }

                if ($token->marketing_campaign_post_version_id && $token->marketing_campaign_post_version_id !== $post->current_version_id) {
                    throw new ClientReviewVersionConflictException;
                }

                if ($post->status !== MarketingCampaignPostStatus::SentToClient) {
                    throw new ClientReviewStateConflictException;
                }

                $client = $post->campaign->client;

                $token->update(['used_at' => now()]);

                $post->comments()->create([
                    'marketing_campaign_post_version_id' => $post->current_version_id,
                    'client_name' => $client->name,
                    'client_email' => $client->email,
                    'body' => 'Approvato senza modifiche.',
                    'visibility' => MarketingCampaignPostCommentVisibility::Client->value,
                    'type' => MarketingCampaignPostCommentType::Approval->value,
                ]);

                $post->update([
                    'status' => MarketingCampaignPostStatus::ClientApproved->value,
                    'client_approved_at' => now(),
                ]);
            });

            $this->successMessage = 'Post approvato con successo. Grazie!';

        } catch (ClientReviewTokenUsedException|
                 ClientReviewTokenExpiredException|
                 \App\Exceptions\Social\ClientReviewVersionConflictException|
                 ClientReviewStateConflictException $e) {
                     $this->addError('review', $e->getMessage());
                 } catch (Exception $e) {
                     Log::error('Errore durante l\'approvazione lato client', ['error' => $e->getMessage()]);
                     $this->addError('review', 'Si è verificato un errore durante l\'elaborazione. Riprova più tardi.');
                 }
    }

    public function requestChanges()
    {
        if ($this->mediaResolutionFailed) {
            abort(409, 'Impossibile caricare in modo sicuro i media di questa versione. Contatta il team per ricevere un nuovo link.');
        }

        $this->validate([
            'commentBody' => 'required|string|max:5000',
        ]);

        try {
            DB::transaction(function () {
                $token = ClientReviewToken::query()
                    ->whereKey($this->tokenRecord->id)
                    ->where('reviewable_type', MarketingCampaignPost::class)
                    ->lockForUpdate()
                    ->firstOrFail();
                $post = MarketingCampaignPost::query()
                    ->with('campaign.client')
                    ->lockForUpdate()
                    ->findOrFail($token->reviewable_id);

                if ($token->isUsed()) {
                    throw new ClientReviewTokenUsedException;
                }

                if ($token->isExpired()) {
                    throw new ClientReviewTokenExpiredException;
                }

                if ($token->marketing_campaign_post_version_id && $token->marketing_campaign_post_version_id !== $post->current_version_id) {
                    throw new ClientReviewVersionConflictException;
                }

                if ($post->status !== MarketingCampaignPostStatus::SentToClient) {
                    throw new ClientReviewStateConflictException;
                }

                $client = $post->campaign->client;

                $token->update(['used_at' => now()]);

                $post->comments()->create([
                    'marketing_campaign_post_version_id' => $post->current_version_id,
                    'client_name' => $client->name,
                    'client_email' => $client->email,
                    'body' => $this->commentBody,
                    'visibility' => MarketingCampaignPostCommentVisibility::Client->value,
                    'type' => MarketingCampaignPostCommentType::ChangeRequest->value,
                ]);

                $post->update([
                    'status' => MarketingCampaignPostStatus::ClientChangesRequested->value,
                ]);
            });

            $this->successMessage = 'Richiesta di modifiche inviata con successo. Il team si metterà al lavoro a breve.';

        } catch (ClientReviewTokenUsedException|
                 ClientReviewTokenExpiredException|
                 \App\Exceptions\Social\ClientReviewVersionConflictException|
                 ClientReviewStateConflictException $e) {
                     $this->addError('review', $e->getMessage());
                 } catch (Exception $e) {
                     Log::error('Errore durante la richiesta di modifiche lato client', ['error' => $e->getMessage()]);
                     $this->addError('review', 'Si è verificato un errore durante l\'elaborazione. Riprova più tardi.');
                 }
    }

    #[Layout('layouts.guest')]
    public function render()
    {
        if ($this->mediaResolutionFailed) {
            abort(409, 'Impossibile caricare in modo sicuro i media di questa versione. Contatta il team per ricevere un nuovo link.');
        }

        return view('livewire.public.marketing-campaign-post-review', [
            'resolvedReviewMedia' => $this->resolvedReviewMedia,
        ]);
    }
}
