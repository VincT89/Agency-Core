<?php

namespace App\Livewire\Client\Social;

use App\Models\SocialPostReviewToken;
use App\Domain\Social\Actions\ClientRespondToSocialPostAction;
use Livewire\Component;

class SocialPostReview extends Component
{
    public $tokenObj;
    public $post;
    
    // Campi Form
    public $clientName = '';
    public $clientEmail = '';
    public $commentBody = '';
    public $isExpired = false;

    public function mount(string $token)
    {
        $this->tokenObj = \App\Models\ClientReviewToken::where('token', $token)->firstOrFail();

        if ($this->tokenObj->expires_at && $this->tokenObj->expires_at->isPast()) {
            $this->isExpired = true;
        }

        $this->post = $this->tokenObj->reviewable->load(['currentVersion', 'clientComments']);

        // Autocompleta se possibile
        $this->clientName = $this->post->client->name ?? '';
    }

    public function approve(ClientRespondToSocialPostAction $action)
    {
        if ($this->isExpired) {
            abort(403, 'Questo link è scaduto. Contatta il team marketing.');
        }

        if ($this->tokenObj->used_at) {
            abort(403, 'Questo link è già stato utilizzato per inviare una risposta.');
        }

        $this->validateFormIfCommented();

        $action->execute(
            token: $this->tokenObj,
            actionType: 'approve',
            commentBody: $this->commentBody,
            clientName: $this->clientName,
            clientEmail: $this->clientEmail
        );
        


        $this->refreshData();
        session()->flash('success', 'Grazie! Il post è stato approvato con successo.');
    }

    public function requestChanges(ClientRespondToSocialPostAction $action)
    {
        if ($this->isExpired) {
            abort(403, 'Questo link è scaduto. Contatta il team marketing.');
        }

        if ($this->tokenObj->used_at) {
            abort(403, 'Questo link è già stato utilizzato per inviare una risposta.');
        }

        $this->validate([
            'commentBody' => 'required|string|min:5|max:2000',
            'clientName' => 'required|string|max:100',
        ]);

        $action->execute(
            token: $this->tokenObj,
            actionType: 'request_changes',
            commentBody: $this->commentBody,
            clientName: $this->clientName,
            clientEmail: $this->clientEmail
        );
        


        $this->refreshData();
        session()->flash('success', 'Richiesta di modifica inviata con successo. Il nostro team ti aggiornerà presto.');
    }

    public function addComment(ClientRespondToSocialPostAction $action)
    {
        if ($this->isExpired) {
            abort(403, 'Questo link è scaduto. Contatta il team marketing.');
        }

        $this->validate([
            'commentBody' => 'required|string|min:2|max:2000',
            'clientName' => 'required|string|max:100',
        ]);

        $action->execute(
            token: $this->tokenObj,
            actionType: 'comment',
            commentBody: $this->commentBody,
            clientName: $this->clientName,
            clientEmail: $this->clientEmail
        );

        $this->refreshData();
        session()->flash('success', 'Commento aggiunto.');
    }

    protected function validateFormIfCommented()
    {
        if (!empty($this->commentBody)) {
            $this->validate([
                'commentBody' => 'string|max:2000',
                'clientName' => 'required|string|max:100',
            ]);
        }
    }

    protected function refreshData()
    {
        $this->tokenObj->refresh();
        $this->post->refresh();
        $this->post->load(['clientComments']);
        $this->commentBody = '';
    }

    public function render()
    {
        // Usa un layout "guest" o pubblico pulito
        return view('livewire.client.social.social-post-review')
            ->layout('layouts.guest', ['title' => 'Revisione Post: ' . $this->post->title]);
    }
}
