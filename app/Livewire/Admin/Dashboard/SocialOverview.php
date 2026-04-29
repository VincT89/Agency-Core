<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;
use Livewire\Component;

class SocialOverview extends Component
{
    public function render()
    {
        // Estrai i conteggi per lo stato operativo dei post
        $internalReviewCount = SocialPost::where('status', SocialPostStatus::InternalReview)->count();
        $regeneratingCount = SocialPost::where('status', SocialPostStatus::Regenerating)->count();
        $readyForClientCount = SocialPost::where('status', SocialPostStatus::ReadyForClient)->count();
        $sentToClientCount = SocialPost::where('status', SocialPostStatus::SentToClient)->count();
        $clientChangesCount = SocialPost::where('status', SocialPostStatus::ClientChangesRequested)->count();
        $clientApprovedCount = SocialPost::where('status', SocialPostStatus::ClientApproved)->count();

        // Recupera i post che richiedono intervento manuale (es. in revisione, bloccati)
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

        // Segnala i post in attesa del cliente da oltre 48h
        $staleSentToClient = SocialPost::where('status', SocialPostStatus::SentToClient)
            ->where('sent_to_client_at', '<', now()->subHours(48))
            ->get();

        // Segnala i processi n8n presumibilmente bloccati (più di 1 ora)
        $staleRegenerating = SocialPost::where('status', SocialPostStatus::Regenerating)
            ->where('updated_at', '<', now()->subHour())
            ->get();

        // Segnala i post stagnanti in revisione interna da oltre 24h
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
