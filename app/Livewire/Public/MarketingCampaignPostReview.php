<?php

namespace App\Livewire\Public;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ClientReviewToken;
use App\Models\MarketingCampaignPost;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostCommentVisibility;
use App\Enums\Social\MarketingCampaignPostCommentType;
use App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver;
use App\Domain\Social\Services\MarketingCampaignPostMediaUrlResolver;
use App\Domain\Social\Exceptions\MarketingCampaignPostMediaResolutionException;
use Illuminate\Support\Facades\Log;
use Exception;

class MarketingCampaignPostReview extends Component
{
    public ClientReviewToken $tokenRecord;
    public MarketingCampaignPost $post;

    public $clientName = '';
    public $clientEmail = '';
    public $commentBody = '';
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
        } catch (MarketingCampaignPostMediaResolutionException | \App\Domain\Social\Exceptions\MarketingCampaignPostMediaDeliveryException $e) {
            $this->mediaResolutionFailed = true;
            Log::error('social.public_review.media_resolution_failed', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function approve()
    {
        if ($this->mediaResolutionFailed) {
            abort(409, 'Impossibile caricare in modo sicuro i media di questa versione. Contatta il team per ricevere un nuovo link.');
        }


        $this->validate([
            'clientName' => 'required|string|max:255',
            'clientEmail' => 'required|email|max:255',
        ]);

        $updated = ClientReviewToken::whereKey($this->tokenRecord->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        if ($updated === 0) {
            session()->flash('error', 'Questo link di revisione è già stato utilizzato.');
            return redirect()->route('public.marketing-campaign-posts.review', ['token' => $this->tokenRecord->token]);
        }

        $this->post->comments()->create([
            'marketing_campaign_post_version_id' => $this->post->current_version_id,
            'client_name' => $this->clientName,
            'client_email' => $this->clientEmail,
            'body' => 'Approvato senza modifiche.',
            'visibility' => MarketingCampaignPostCommentVisibility::Client->value,
            'type' => MarketingCampaignPostCommentType::Approval->value,
        ]);

        $this->post->update([
            'status' => MarketingCampaignPostStatus::ClientApproved->value,
            'client_approved_at' => now(),
        ]);

        session()->flash('success', 'Post approvato con successo. Grazie!');
        return redirect()->route('public.marketing-campaign-posts.review', ['token' => $this->tokenRecord->token]);
    }

    public function requestChanges()
    {
        if ($this->mediaResolutionFailed) {
            abort(409, 'Impossibile caricare in modo sicuro i media di questa versione. Contatta il team per ricevere un nuovo link.');
        }


        $this->validate([
            'clientName' => 'required|string|max:255',
            'clientEmail' => 'required|email|max:255',
            'commentBody' => 'required|string',
        ]);

        $updated = ClientReviewToken::whereKey($this->tokenRecord->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        if ($updated === 0) {
            session()->flash('error', 'Questo link di revisione è già stato utilizzato.');
            return redirect()->route('public.marketing-campaign-posts.review', ['token' => $this->tokenRecord->token]);
        }

        $this->post->comments()->create([
            'marketing_campaign_post_version_id' => $this->post->current_version_id,
            'client_name' => $this->clientName,
            'client_email' => $this->clientEmail,
            'body' => $this->commentBody,
            'visibility' => MarketingCampaignPostCommentVisibility::Client->value,
            'type' => MarketingCampaignPostCommentType::ChangeRequest->value,
        ]);

        $this->post->update([
            'status' => MarketingCampaignPostStatus::ClientChangesRequested->value,
        ]);

        session()->flash('success', 'Richiesta di modifiche inviata con successo. Il team si metterà al lavoro a breve.');
        return redirect()->route('public.marketing-campaign-posts.review', ['token' => $this->tokenRecord->token]);
    }

    #[Layout('layouts.guest')]
    public function render()
    {
        if ($this->mediaResolutionFailed) {
            abort(409, 'Impossibile caricare in modo sicuro i media di questa versione. Contatta il team per ricevere un nuovo link.');
        }

        return view('livewire.public.marketing-campaign-post-review', [
            'resolvedReviewMedia' => $this->resolvedReviewMedia
        ]);
    }
}
