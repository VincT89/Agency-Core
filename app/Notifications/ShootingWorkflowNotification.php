<?php

namespace App\Notifications;

use App\Enums\Shooting\ShootingWorkflowEvent;
use App\Helpers\ShootingRouteResolver;
use App\Models\Shooting\Shoot;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShootingWorkflowNotification extends Notification
{
    use Queueable;

    public ShootingWorkflowEvent $event;
    public string $title;
    public string $body;
    public string $url;
    public int $shootId;

    public function __construct(ShootingWorkflowEvent $event, string $title, string $body, string $url, int $shootId)
    {
        $this->event = $event;
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
        $this->shootId = $shootId;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $shoot = Shoot::find($this->shootId);

        $resolvedUrl = $this->url;
        if ($notifiable instanceof User && $shoot) {
            $resolvedUrl = ShootingRouteResolver::showRouteFor($notifiable, $shoot);
        }

        return [
            'type' => $this->event->value,
            'category' => 'shooting',
            'title' => $this->title,
            'message' => $this->body,
            'url' => $resolvedUrl,
            'intended_url' => $resolvedUrl,
            'shoot_id' => $this->shootId,
            'meta' => $shoot ? [
                'shoot_code' => $shoot->code,
                'project_id' => $shoot->project_id,
                'client_id' => $shoot->project?->client_id
                    ?? $shoot->marketingCampaign?->client_id,
            ] : [],
        ];
    }
}
