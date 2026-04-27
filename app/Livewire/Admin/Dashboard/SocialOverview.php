<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;
use Livewire\Component;

class SocialOverview extends Component
{
    public function render()
    {
        // KPI: Counts by status
        $internalReviewCount = SocialPost::where('status', SocialPostStatus::InternalReview)->count();
        $regeneratingCount = SocialPost::where('status', SocialPostStatus::Regenerating)->count();
        $readyForClientCount = SocialPost::where('status', SocialPostStatus::ReadyForClient)->count();
        $sentToClientCount = SocialPost::where('status', SocialPostStatus::SentToClient)->count();
        $clientChangesCount = SocialPost::where('status', SocialPostStatus::ClientChangesRequested)->count();
        $clientApprovedCount = SocialPost::where('status', SocialPostStatus::ClientApproved)->count();

        // Operative List: Ultimi post che richiedono attenzione (escludo quelli pronti/inviati ma senza modifiche, o approvati)
        // Diamo priorità a: in revisione interna, modifiche richieste, rigenerazione in corso
        $attentionPosts = SocialPost::with(['client', 'project', 'currentVersion'])
            ->whereIn('status', [
                SocialPostStatus::InternalReview,
                SocialPostStatus::ClientChangesRequested,
                SocialPostStatus::ChangesRequested,
                SocialPostStatus::Regenerating
            ])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // Alert Veri:
        // 1. Inviato al cliente da > 48h
        $staleSentToClient = SocialPost::where('status', SocialPostStatus::SentToClient)
            ->where('sent_to_client_at', '<', now()->subHours(48))
            ->get();

        // 2. Rigenerazione in corso da > 1 ora (presunto bloccato)
        $staleRegenerating = SocialPost::where('status', SocialPostStatus::Regenerating)
            ->where('updated_at', '<', now()->subHour())
            ->get();

        // 3. In revisione interna da > 24h senza modifiche o passaggi
        $staleInternalReview = SocialPost::where('status', SocialPostStatus::InternalReview)
            ->where('updated_at', '<', now()->subHours(24))
            ->get();

        return view('livewire.admin.dashboard.social-overview', [
            'internalReviewCount' => $internalReviewCount,
            'regeneratingCount' => $regeneratingCount,
            'readyForClientCount' => $readyForClientCount,
            'sentToClientCount' => $sentToClientCount,
            'clientChangesCount' => $clientChangesCount,
            'clientApprovedCount' => $clientApprovedCount,
            'attentionPosts' => $attentionPosts,
            'staleSentToClient' => $staleSentToClient,
            'staleRegenerating' => $staleRegenerating,
            'staleInternalReview' => $staleInternalReview,
        ]);
    }
}
