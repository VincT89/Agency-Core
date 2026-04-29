<?php

namespace App\Domain\Dashboard\DTOs;

class PhotographerDashboardData
{
    public function __construct(
        public readonly int $kpi_da_rispondere,
        public readonly int $kpi_in_attesa_cliente,
        public readonly int $kpi_pianificati,
        
        // Richieste in attesa di conferma del fotografo
        public readonly array $queue_da_rispondere,
        
        // Attività programmate per oggi
        public readonly array $queue_oggi,
        
        // Attività in attesa del cliente
        public readonly array $queue_in_attesa_cliente,
        
        // Task generali in arrivo
        public readonly array $upcoming_tasks = []
    ) {}
}
