<?php

namespace App\Domain\Dashboard\Queries;

use App\Models\Shooting\Shoot;
use App\Models\SocialPost;
use App\Models\EditorialSlot;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\EditorialSlotStatus;
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
                    bucket: 'issue', // Alta priorità per admin
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

        // Social Data
        $socialApprovedNotScheduled = SocialPost::where('status', SocialPostStatus::ClientApproved)
            ->whereDoesntHave('activeEditorialSlot')
            ->count();
            
        $socialScheduledThisWeek = EditorialSlot::where('status', EditorialSlotStatus::Scheduled)
            ->whereBetween('scheduled_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();
            
        $socialPublishToday = EditorialSlot::where('status', EditorialSlotStatus::Scheduled)
            ->whereDate('scheduled_at', Carbon::today())
            ->count();

        // Social Attention List: Scaduti non pubblicati
        $pastDueSlots = EditorialSlot::with('post.project')
            ->where('status', EditorialSlotStatus::Scheduled)
            ->where('scheduled_at', '<', Carbon::now())
            ->get();
            
        foreach ($pastDueSlots as $slot) {
            $attentionList[] = new WorkQueueItemData(
                bucket: 'issue',
                shoot_id: $slot->social_post_id,
                shoot_code: 'SOC-' . $slot->social_post_id,
                shoot_name: $slot->post->title ?? 'Post',
                project_name: $slot->post->project->name ?? '',
                status_label: 'Pubblicazione Scaduta',
                action_label: 'Vedi Post',
                action_url: route('social.posts.show', $slot->social_post_id),
                priority: 1,
                reason_code: 'social_past_due'
            );
        }

        // Ordinamento per priorità: waiting_client/social_past_due (1), client_rejected (2), waiting_photographer (3)
        usort($attentionList, fn($a, $b) => $a->priority <=> $b->priority);

        // Prendi solo i primi 10 da far vedere
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
            health_warnings: [] // Da usare in futuro se serve
        );
    }
}
