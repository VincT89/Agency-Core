<?php

namespace App\Livewire\Client;

use Livewire\Component;
use App\Models\EditorialPlan;
use App\Models\ClientReviewToken;
use App\Domain\Social\Actions\ClientRespondToEditorialPlanAction;

class EditorialPlanReview extends Component
{
    public EditorialPlan $plan;
    public string $token;
    public $comment = '';
    public $clientName = '';
    public $isExpired = false;
    public $showChangesForm = false;
    public $tokenObj = null;

    public function mount(EditorialPlan $plan, string $token)
    {
        $this->plan = $plan->load(['marketingProject', 'slots.socialPost.currentVersion']);
        $this->token = $token;

        $reviewToken = ClientReviewToken::where('token', $token)->first();
        $this->tokenObj = $reviewToken;
        if ($reviewToken && $reviewToken->expires_at && $reviewToken->expires_at->isPast()) {
            $this->isExpired = true;
        }
    }

    public function approve(ClientRespondToEditorialPlanAction $action)
    {
        if ($this->isExpired) {
            abort(403, 'Questo link è scaduto. Contatta il team marketing.');
        }

        $reviewToken = ClientReviewToken::where('token', $this->token)->first();
        if ($reviewToken && $reviewToken->used_at) {
            abort(403, 'Questo link è già stato utilizzato per inviare una risposta.');
        }

        $action->execute($this->plan, 'approve');
        
        $reviewToken = ClientReviewToken::where('token', $this->token)->first();
        if ($reviewToken) {
            $reviewToken->update(['used_at' => now()]);
        }

        $this->plan->refresh();
        session()->flash('success', 'Piano Editoriale approvato con successo! Grazie.');
    }

    public function requestChanges(ClientRespondToEditorialPlanAction $action)
    {
        if ($this->isExpired) {
            abort(403, 'Questo link è scaduto. Contatta il team marketing.');
        }

        $this->validate([
            'comment' => 'required|string|min:5|max:2000',
            'clientName' => 'required|string|max:255',
        ]);

        $reviewToken = ClientReviewToken::where('token', $this->token)->first();
        if ($reviewToken && $reviewToken->used_at) {
            abort(403, 'Questo link è già stato utilizzato per inviare una risposta.');
        }

        $action->execute($this->plan, 'request_changes', $this->comment);

        // WORKAROUND: Salva temporaneamente il feedback sul primo post disponibile in assenza di tabella commenti globale
        \App\Models\SocialPostComment::create([
            'social_post_id' => $this->plan->slots->first()->social_post_id ?? null,
            'client_name' => $this->clientName,
            'body' => "REVISIONE PIANO: " . $this->comment,
            'visibility' => \App\Enums\Social\SocialPostCommentVisibility::Client,
            'type' => \App\Enums\Social\SocialPostCommentType::ChangeRequest,
        ]);

        $reviewToken = ClientReviewToken::where('token', $this->token)->first();
        if ($reviewToken) {
            $reviewToken->update(['used_at' => now()]);
        }

        $this->plan->refresh();
        session()->flash('success', 'Richiesta di modifica inviata. Il nostro team la prenderà in carico a breve.');
    }

    public function render()
    {
        return view('livewire.client.editorial-plan-review');
    }
}
