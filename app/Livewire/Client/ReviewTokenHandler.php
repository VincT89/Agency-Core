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
            \Illuminate\Support\Facades\Log::warning('Client review token expired attempt', [
                'token_id' => $reviewToken->id,
                'reviewable_id' => $reviewToken->reviewable_id,
            ]);
            abort(403, 'Il link è scaduto. Contatta il team marketing.');
        }

        $this->reviewable = $reviewToken->reviewable;
    }

    public function render()
    {
        return view('livewire.client.review-token-handler')->layout('layouts.guest', ['title' => 'Revisione Materiale']);
    }
}
