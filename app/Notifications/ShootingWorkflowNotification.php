<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Enums\Shooting\ShootingWorkflowEvent;

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
        $shoot = \App\Models\Shooting\Shoot::find($this->shootId);
        
        $resolvedUrl = $this->url;
        if ($notifiable instanceof \App\Models\User && $shoot) {
            $resolvedUrl = \App\Helpers\ShootingRouteResolver::showRouteFor($notifiable, $shoot);
        }

        return [
            'type' => $this->event->value,
            'title' => $this->title,
            'message' => $this->body,
            'url' => url('/shoots/' . $this->shootId), // Usa il redirect controller per routing dinamico
            'intended_url' => $resolvedUrl, // Salva la destinazione originaria intesa
            'intended_route' => $resolvedUrl, // Duplicate as requested by user to ensure compatibility
            'shoot_id' => $this->shootId,
            'meta' => $shoot ? [
                'shoot_code' => $shoot->code,
                'project_id' => $shoot->project_id,
                'client_id' => $shoot->project->client_id ?? null,
            ] : [],
        ];
    }
}
