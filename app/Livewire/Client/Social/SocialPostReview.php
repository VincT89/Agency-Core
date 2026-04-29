<?php

namespace App\Livewire\Client\Social;

use App\Models\SocialPostReviewToken;
use App\Domain\Social\Actions\ClientRespondToSocialPostAction;
use Livewire\Component;

class SocialPostReview extends Component
{
    public $tokenObj;
    public $post;
    
    // Variabili del form di revisione
    public $clientName = '';
    public $clientEmail = '';
    public $feedback = '';
    public $isExpired = false;
    public $showChangesForm = false;
    public bool $hasReadContent = false;

    public function mount(string $token)
    {
        $this->tokenObj = \App\Models\ClientReviewToken::where('token', $token)->firstOrFail();

        if ($this->tokenObj->expires_at && $this->tokenObj->expires_at->isPast()) {
            $this->isExpired = true;
        }

        $this->post = $this->tokenObj->reviewable;

        if (!$this->isExpired && !$this->tokenObj->used_at) {
            $this->post->load(['currentVersion', 'clientComments']);
            $this->clientName = $this->post->client->name ?? '';
        }
    }

    public function approve(ClientRespondToSocialPostAction $action)
    {
        if ($this->isExpired) {
            abort(403, 'Questo link è scaduto. Contatta il team marketing.');
        }

        if ($this->tokenObj->used_at) {
            abort(403, 'Questo link è già stato utilizzato per inviare una risposta.');
        }

        $this->validate([
            'clientName' => ['required', 'string', 'max:255'],
            'hasReadContent' => ['accepted'],
        ], [
            'hasReadContent.accepted' => 'È obbligatorio dichiarare di aver visionato il contenuto.',
        ]);

        $action->execute(
            token: $this->tokenObj,
            actionType: 'approve',
            commentBody: null,
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
            'clientName' => ['required', 'string', 'max:255'],
            'feedback' => ['required', 'string', 'min:5'],
        ]);

        $action->execute(
            token: $this->tokenObj,
            actionType: 'request_changes',
            commentBody: $this->feedback,
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
            'feedback' => 'required|string|min:2|max:2000',
            'clientName' => 'required|string|max:100',
        ]);

        $action->execute(
            token: $this->tokenObj,
            actionType: 'comment',
            commentBody: $this->feedback,
            clientName: $this->clientName,
            clientEmail: $this->clientEmail
        );

        $this->refreshData();
        session()->flash('success', 'Commento aggiunto.');
    }

    protected function validateFormIfCommented()
    {
        if (!empty($this->feedback)) {
            $this->validate([
                'feedback' => 'string|max:2000',
                'clientName' => 'required|string|max:100',
            ]);
        }
    }

    protected function refreshData()
    {
        $this->tokenObj->refresh();
        $this->post->refresh();
        $this->post->load(['clientComments']);
        $this->feedback = '';
    }

    public function render()
    {
        // Utilizza il layout pubblico senza navigazione amministrativa
        return view('livewire.client.social.social-post-review')
            ->layout('layouts.guest', ['title' => 'Revisione Post: ' . $this->post->title]);
    }
}
