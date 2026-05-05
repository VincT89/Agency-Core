<?php

namespace App\Livewire\Public;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ClientReviewToken;
use App\Models\MarketingCampaignPost;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostCommentVisibility;
use App\Enums\Social\MarketingCampaignPostCommentType;
use Exception;

class MarketingCampaignPostReview extends Component
{
    public ClientReviewToken $tokenRecord;
    public MarketingCampaignPost $post;

    public $clientName = '';
    public $clientEmail = '';
    public $commentBody = '';

    public function mount(string $token)
    {
        $this->tokenRecord = ClientReviewToken::where('token', $token)
            ->where('reviewable_type', MarketingCampaignPost::class)
            ->firstOrFail();

        if ($this->tokenRecord->isExpired() || $this->tokenRecord->isUsed()) {
            abort(403, 'Questo link di revisione è scaduto o è già stato utilizzato.');
        }

        $this->post = $this->tokenRecord->reviewable;
        
        $client = $this->post->campaign->client;
        $this->clientName = $client->name;
        $this->clientEmail = $client->email;
    }

    public function approve()
    {
        $this->validate([
            'clientName' => 'required|string|max:255',
            'clientEmail' => 'required|email|max:255',
        ]);

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

        $this->tokenRecord->markAsUsed();

        session()->flash('success', 'Post approvato con successo. Grazie!');
        return redirect()->route('public.marketing-campaign-posts.review', ['token' => $this->tokenRecord->token]);
    }

    public function requestChanges()
    {
        $this->validate([
            'clientName' => 'required|string|max:255',
            'clientEmail' => 'required|email|max:255',
            'commentBody' => 'required|string',
        ]);

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

        $this->tokenRecord->markAsUsed();

        session()->flash('success', 'Richiesta di modifiche inviata con successo. Il team si metterà al lavoro a breve.');
        return redirect()->route('public.marketing-campaign-posts.review', ['token' => $this->tokenRecord->token]);
    }

    #[Layout('layouts.guest')]
    public function render()
    {
        return view('livewire.public.marketing-campaign-post-review');
    }
}
