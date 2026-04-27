<?php

namespace App\Domain\Dashboard\DTOs;

class PhotographerDashboardData
{
    public function __construct(
        public readonly int $kpi_da_rispondere,
        public readonly int $kpi_in_attesa_cliente,
        public readonly int $kpi_pianificati,
        
        /** @var WorkQueueItemData[] */
        public readonly array $queue_da_rispondere,
        
        /** @var WorkQueueItemData[] */
        public readonly array $queue_oggi,
        
        /** @var WorkQueueItemData[] */
        public readonly array $queue_in_attesa_cliente,
        
        /** @var \App\Models\Task[] */
        public readonly array $upcoming_tasks = []
    ) {}
}
