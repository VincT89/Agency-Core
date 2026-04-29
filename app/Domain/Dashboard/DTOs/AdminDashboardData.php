<?php

namespace App\Domain\Dashboard\DTOs;

class AdminDashboardData
{
    public function __construct(
        public readonly int $kpi_shooting_attivi,
        public readonly int $kpi_waiting_photographer,
        public readonly int $kpi_waiting_client,
        public readonly int $kpi_client_rejected,
        public readonly int $kpi_scheduled,

        // KPI area Social
        public readonly int $kpi_social_approved_not_scheduled,
        public readonly int $kpi_social_scheduled_this_week,
        public readonly int $kpi_social_publish_today,
        
        // Elementi prioritari per l'admin
        public readonly array $attention_list,
        
        // Allarmi di sistema
        public readonly array $health_warnings
    ) {}
}
