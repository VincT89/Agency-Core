<?php

namespace App\Livewire\Client;

use Livewire\Component;
use App\Models\ClientReviewToken;

class ReviewTokenHandler extends Component
{
    public $token;
    public $reviewable;

    public function mount($token)
    {
        $this->token = $token;
        $reviewToken = ClientReviewToken::with('reviewable')->where('token', $token)->firstOrFail();

        if ($reviewToken->expires_at && $reviewToken->expires_at->isPast()) {
            abort(403, 'Questo link è scaduto.');
        }

        $this->reviewable = $reviewToken->reviewable;
    }

    public function render()
    {
        return view('livewire.client.review-token-handler')->layout('layouts.guest', ['title' => 'Revisione Materiale']);
    }
}
