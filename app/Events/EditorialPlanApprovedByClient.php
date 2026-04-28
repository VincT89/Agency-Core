<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EditorialPlanApprovedByClient
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public \App\Models\EditorialPlan $plan;

    /**
     * Create a new event instance.
     */
    public function __construct(\App\Models\EditorialPlan $plan)
    {
        $this->plan = $plan;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
