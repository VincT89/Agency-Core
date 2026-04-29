<?php

namespace App\Domain\Shooting\Actions;

use App\Models\Shooting\Shoot;
use App\Models\CalendarEvent;
use App\Models\Task;
use App\Models\User;
use App\Enums\Shooting\ShootStatus;
use App\Enums\Shooting\ShootSlotStatus;
use App\Enums\Shooting\ShootingWorkflowEvent;
use App\Notifications\ShootingWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientConfirmAction
{
    public function execute(Shoot $shoot, bool $accepted, int $adminId): void
    {
        if ($shoot->status !== ShootStatus::WaitingClient) {
            throw new \Exception('Lo shooting non è in attesa del cliente.');
        }

        if ($accepted && ($shoot->calendar_event_id || $shoot->task_id)) {
            throw new \DomainException('Shooting già confermato: Evento o Task già esistenti.');
        }

        DB::transaction(function () use ($shoot, $accepted, $adminId) {
            
            if ($accepted) {
                if (!$shoot->selectedSlot) {
                    throw new \Exception("Impossibile confermare: nessuno slot selezionato dal fotografo.");
                }

                $slot = $shoot->selectedSlot;

                $tz = config('app.timezone');
                $startAt = Carbon::parse($slot->date->format('Y-m-d') . ' ' . $slot->starts_at, $tz);
                $endAt = Carbon::parse($slot->date->format('Y-m-d') . ' ' . $slot->ends_at, $tz);

                // Fissa a calendario l'appuntamento per lo shooting
                $event = CalendarEvent::create([
                    'client_id' => $shoot->project->client_id,
                    'project_id' => $shoot->project_id,
                    'created_by' => $adminId,
                    'assigned_to' => $shoot->photographer_id,
                    'title' => 'Shooting: ' . $shoot->project->name,
                    'description' => "Shooting programmato.\nNote cliente: {$shoot->client_notes}\nNote interne: {$shoot->internal_notes}",
                    'type' => 'other',
                    'status' => 'scheduled',
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'is_all_day' => false,
                    'location' => $shoot->location,
                ]);

                // Crea la task operativa per il fotografo
                $task = Task::create([
                    'project_id' => $shoot->project_id,
                    'created_by' => $adminId,
                    'assigned_to' => $shoot->photographer_id,
                    'title' => 'Shooting: ' . $shoot->project->name,
                    'description' => "Effettuare shooting.\nData: {$slot->date->format('d/m/Y')} ({$slot->starts_at} - {$slot->ends_at})\nLocation: {$shoot->location}",
                    'status' => 'todo',
                    'priority' => 'high',
                    'due_date' => $slot->date,
                ]);

                // Aggiorna lo stato dello shooting agganciando evento e task
                $shoot->update([
                    'status' => ShootStatus::Scheduled,
                    'client_confirmation_status' => 'accepted',
                    'client_confirmed_at' => now(),
                    'calendar_event_id' => $event->id,
                    'task_id' => $task->id,
                ]);
                
                $this->notifyAll($shoot, ShootingWorkflowEvent::ClientConfirmed, 'Shooting Confermato', "Il cliente ha confermato lo shooting {$shoot->code}. Evento a calendario e Task creati.");

            } else {
                // Il cliente ha rifiutato: ripristina gli slot per una nuova proposta
                $shoot->slots()->update(['status' => ShootSlotStatus::Proposed]);
                
                $shoot->update([
                    'status' => ShootStatus::ClientRejected,
                    'client_confirmation_status' => 'rejected',
                    'client_confirmed_at' => now(),
                    'selected_slot_id' => null, // Rimuove il vincolo dello slot
                ]);
                
                $this->notifyAll($shoot, ShootingWorkflowEvent::ClientRejected, 'Shooting Rifiutato', "Il cliente ha rifiutato lo shooting {$shoot->code}.");
            }
        });
    }

    private function notifyAll(Shoot $shoot, ShootingWorkflowEvent $event, string $title, string $message): void
    {
        $usersToNotify = User::where('role', 'admin')
            ->orWhere('id', $shoot->created_by)
            ->orWhere('id', $shoot->photographer_id)
            ->get()
            ->unique('id');
            
        foreach ($usersToNotify as $user) {
            $url = \App\Helpers\ShootingRouteResolver::showRouteFor($user, $shoot);
            $user->notify(new ShootingWorkflowNotification(
                $event,
                $title,
                $message,
                $url,
                $shoot->id
            ));
        }
    }
}
