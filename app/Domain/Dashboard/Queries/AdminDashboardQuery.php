<?php

namespace App\Domain\Dashboard\Queries;

use App\Models\Shooting\Shoot;

use App\Domain\Dashboard\DTOs\AdminDashboardData;
use App\Domain\Dashboard\DTOs\WorkQueueItemData;
use Illuminate\Support\Carbon;

class AdminDashboardQuery
{
    public function getDashboardData(): AdminDashboardData
    {
        $activeShootsQuery = Shoot::with(['project', 'photographer'])
            ->whereNotIn('status', ['draft', 'completed', 'archived', 'cancelled']);
            
        $shoots = $activeShootsQuery->get();

        $shootingAttivi = $shoots->count();
        $waitingPhotographer = 0;
        $waitingClient = 0;
        $clientRejected = 0;
        $scheduled = 0;
        
        $attentionList = [];

        foreach ($shoots as $shoot) {
            $status = $shoot->status->value;
            
            if ($status === 'waiting_photographer') {
                $waitingPhotographer++;
                $attentionList[] = new WorkQueueItemData(
                    bucket: 'pending',
                    shoot_id: $shoot->id,
                    shoot_code: $shoot->code,
                    shoot_name: $shoot->title,
                    project_name: $shoot->project->name ?? '',
                    status_label: 'In attesa Fotografo',
                    action_label: 'Apri',
                    action_url: route('admin.shooting.show', $shoot->id),
                    priority: 3,
                    reason_code: 'waiting_photographer'
                );
            } elseif ($status === 'waiting_client') {
                $waitingClient++;
                $attentionList[] = new WorkQueueItemData(
                    bucket: 'issue', // Alta priorità per sblocco flusso
                    shoot_id: $shoot->id,
                    shoot_code: $shoot->code,
                    shoot_name: $shoot->title,
                    project_name: $shoot->project->name ?? '',
                    status_label: 'Da Confermare Cliente',
                    action_label: 'Conferma Cliente',
                    action_url: route('admin.shooting.show', $shoot->id),
                    priority: 1,
                    reason_code: 'waiting_client'
                );
            } elseif ($status === 'client_rejected') {
                $clientRejected++;
                $attentionList[] = new WorkQueueItemData(
                    bucket: 'issue',
                    shoot_id: $shoot->id,
                    shoot_code: $shoot->code,
                    shoot_name: $shoot->title,
                    project_name: $shoot->project->name ?? '',
                    status_label: 'Cliente Rifiutato',
                    action_label: 'Rivedi',
                    action_url: route('admin.shooting.show', $shoot->id),
                    priority: 2,
                    reason_code: 'client_rejected'
                );
            } elseif ($status === 'scheduled') {
                $scheduled++;
            }
        }

        // Statistiche modulo social
        $socialApprovedNotScheduled = \App\Models\MarketingCampaignPost::whereIn('status', [
                \App\Enums\Social\MarketingCampaignPostStatus::ClientApproved,
                \App\Enums\Social\MarketingCampaignPostStatus::Approved
            ])
            ->whereNull('scheduled_date')
            ->count();
            
        $socialScheduledThisWeek = \App\Models\MarketingCampaignPost::whereNotNull('scheduled_date')
            ->whereNotIn('status', [
                \App\Enums\Social\MarketingCampaignPostStatus::Published, 
                \App\Enums\Social\MarketingCampaignPostStatus::Cancelled
            ])
            ->whereBetween('scheduled_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();
            
        $socialPublishToday = \App\Models\MarketingCampaignPost::whereNotNull('scheduled_date')
            ->whereNotIn('status', [
                \App\Enums\Social\MarketingCampaignPostStatus::Published, 
                \App\Enums\Social\MarketingCampaignPostStatus::Cancelled
            ])
            ->whereDate('scheduled_date', Carbon::today())
            ->count();

        // Segnala post non pubblicati nei tempi previsti
        $pastDuePosts = \App\Models\MarketingCampaignPost::with('campaign')
            ->whereNotNull('scheduled_date')
            ->whereNotIn('status', [
                \App\Enums\Social\MarketingCampaignPostStatus::Published, 
                \App\Enums\Social\MarketingCampaignPostStatus::Cancelled
            ])
            ->where('scheduled_date', '<', Carbon::today())
            ->get();
            
        foreach ($pastDuePosts as $post) {
            $attentionList[] = new WorkQueueItemData(
                bucket: 'issue',
                shoot_id: $post->id,
                shoot_code: 'MKT-' . $post->id,
                shoot_name: $post->title ?? 'Post Marketing',
                project_name: $post->campaign->name ?? '',
                status_label: 'Pubblicazione Scaduta',
                action_label: 'Vedi Campagna',
                action_url: route('marketing-campaigns.show', $post->marketing_campaign_id),
                priority: 1,
                reason_code: 'social_past_due'
            );
        }

        // Ordina le urgenze in base alla priorità operativa
        usort($attentionList, fn($a, $b) => $a->priority <=> $b->priority);

        // Limita l'output per non sovraccaricare la UI
        $attentionList = array_slice($attentionList, 0, 10);

        return new AdminDashboardData(
            kpi_shooting_attivi: $shootingAttivi,
            kpi_waiting_photographer: $waitingPhotographer,
            kpi_waiting_client: $waitingClient,
            kpi_client_rejected: $clientRejected,
            kpi_scheduled: $scheduled,
            kpi_social_approved_not_scheduled: $socialApprovedNotScheduled,
            kpi_social_scheduled_this_week: $socialScheduledThisWeek,
            kpi_social_publish_today: $socialPublishToday,
            attention_list: $attentionList,
            health_warnings: [] // Placeholder espandibile in futuro
        );
    }
}
