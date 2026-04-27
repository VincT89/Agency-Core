<?php

namespace App\Domain\Dashboard\DTOs;

class WorkQueueItemData
{
    public function __construct(
        public readonly string $bucket, // 'today', 'pending', 'issue'
        public readonly int $shoot_id,
        public readonly string $shoot_code,
        public readonly string $shoot_name,
        public readonly string $project_name,
        public readonly string $status_label,
        public readonly string $action_label,
        public readonly string $action_url,
        public readonly int $priority, // Lower is higher priority
        public readonly string $reason_code // 'waiting_photographer', 'waiting_client', 'scheduled_today', etc.
    ) {}
}
