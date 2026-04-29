<?php

namespace App\Domain\Dashboard\Queries;

use App\Models\User;
use App\Models\Shooting\Shoot;
use App\Domain\Dashboard\DTOs\PhotographerDashboardData;
use App\Domain\Dashboard\DTOs\WorkQueueItemData;
use Illuminate\Support\Carbon;

class PhotographerDashboardQuery
{
    public function getDashboardData(User $user): PhotographerDashboardData
    {
        $today = Carbon::today();

        // Filtra solo shooting attivi assegnati al fotografo corrente
        $baseQuery = Shoot::with(['project', 'calendarEvent', 'slots'])
            ->where('photographer_id', $user->id)
            ->whereNotIn('status', ['archived', 'cancelled', 'completed', 'draft']);

        $shoots = $baseQuery->get();

        $daRispondere = 0;
        $inAttesaCliente = 0;
        $pianificati = 0;

        $queueDaRispondere = [];
        $queueOggi = [];
        $queueInAttesaCliente = [];

        foreach ($shoots as $shoot) {
            $status = $shoot->status->value;

            // Calcola le metriche della dashboard
            if ($status === 'waiting_photographer') $daRispondere++;
            if ($status === 'waiting_client') $inAttesaCliente++;
            if ($status === 'scheduled') $pianificati++;

            // Classifica gli elementi per coda di lavoro
            
            // Richiede feedback immediato dal fotografo
            if ($status === 'waiting_photographer') {
                $queueDaRispondere[] = new WorkQueueItemData(
                    bucket: 'pending',
                    shoot_id: $shoot->id,
                    shoot_code: $shoot->code,
                    shoot_name: $shoot->title,
                    project_name: $shoot->project->name ?? 'Nessun progetto',
                    status_label: 'Richiesta di disponibilità',
                    action_label: 'Rispondi',
                    action_url: route('photography.shooting.show', $shoot->id),
                    priority: 1,
                    reason_code: 'waiting_photographer'
                );
            }
            // In attesa di validazione da parte del cliente
            elseif ($status === 'waiting_client') {
                $queueInAttesaCliente[] = new WorkQueueItemData(
                    bucket: 'pending',
                    shoot_id: $shoot->id,
                    shoot_code: $shoot->code,
                    shoot_name: $shoot->title,
                    project_name: $shoot->project->name ?? 'Nessun progetto',
                    status_label: 'In attesa Cliente',
                    action_label: 'Apri',
                    action_url: route('photography.shooting.show', $shoot->id),
                    priority: 2,
                    reason_code: 'waiting_client'
                );
            }
            // Shooting programmati per la data odierna
            elseif ($status === 'scheduled') {
                // Verifica la data rispetto agli slot o all'evento a calendario
                $isToday = false;
                if ($shoot->calendarEvent && Carbon::parse($shoot->calendarEvent->start_date)->isToday()) {
                    $isToday = true;
                } else {
                    $selectedSlot = $shoot->slots->firstWhere('id', $shoot->selected_slot_id);
                    if ($selectedSlot && $selectedSlot->date->isToday()) {
                        $isToday = true;
                    }
                }

                if ($isToday) {
                    $queueOggi[] = new WorkQueueItemData(
                        bucket: 'today',
                        shoot_id: $shoot->id,
                        shoot_code: $shoot->code,
                        shoot_name: $shoot->title,
                        project_name: $shoot->project->name ?? 'Nessun progetto',
                        status_label: 'Shooting in Programma',
                        action_label: 'Apri',
                        action_url: route('photography.shooting.show', $shoot->id),
                        priority: 3,
                        reason_code: 'scheduled_today'
                    );
                }
            }
        }

        // Ordina la coda in base all'urgenza operativa
        usort($queueDaRispondere, fn($a, $b) => $a->priority <=> $b->priority);
        usort($queueInAttesaCliente, fn($a, $b) => $a->priority <=> $b->priority);
        usort($queueOggi, fn($a, $b) => $a->priority <=> $b->priority);

        // Estrae le task imminenti assegnate all'utente
        $upcomingTasks = \App\Models\Task::with('project')
            ->assignedTo($user)
            ->open()
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get()
            ->all();

        return new PhotographerDashboardData(
            kpi_da_rispondere: $daRispondere,
            kpi_in_attesa_cliente: $inAttesaCliente,
            kpi_pianificati: $pianificati,
            queue_da_rispondere: $queueDaRispondere,
            queue_oggi: $queueOggi,
            queue_in_attesa_cliente: $queueInAttesaCliente,
            upcoming_tasks: $upcomingTasks
        );
    }
}
