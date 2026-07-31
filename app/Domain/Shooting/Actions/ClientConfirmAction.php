<?php

namespace App\Domain\Shooting\Actions;

use App\Enums\Shooting\ShootingWorkflowEvent;
use App\Enums\Shooting\ShootStatus;
use App\Helpers\ShootingRouteResolver;
use App\Models\CalendarEvent;
use App\Models\Shooting\Shoot;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ShootingWorkflowNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientConfirmAction
{
    public function execute(Shoot $shoot, bool $accepted, int $actorId): void
    {
        if ($shoot->status !== ShootStatus::WaitingClient) {
            throw ValidationException::withMessages([
                'clientResponse' => 'Lo shooting non è più in attesa del cliente.',
            ]);
        }

        if (! $shoot->client_notified_at) {
            throw ValidationException::withMessages([
                'clientChannel' => 'Registra prima l’avvenuta comunicazione al cliente.',
            ]);
        }

        if ($accepted && ($shoot->calendar_event_id || $shoot->task_id)) {
            throw ValidationException::withMessages([
                'clientResponse' => 'Lo shooting è già stato pianificato.',
            ]);
        }

        DB::transaction(function () use ($shoot, $accepted, $actorId) {
            if (! $accepted) {
                $shoot->update([
                    'status' => ShootStatus::ClientRejected,
                    'client_confirmation_status' => 'rejected',
                    'client_confirmed_at' => now(),
                    'selected_slot_id' => null,
                ]);

                $this->notifyAll(
                    $shoot,
                    ShootingWorkflowEvent::ClientRejected,
                    'Nuova proposta richiesta',
                    "Il cliente ha rifiutato la data dello shooting {$shoot->code}. Prepara nuove date."
                );

                return;
            }

            $shoot->loadMissing([
                'project.client',
                'marketingCampaign.client',
                'selectedSlot',
            ]);

            $slot = $shoot->selectedSlot;
            $client = $shoot->clientRecord();

            if (! $slot || ! $client) {
                throw ValidationException::withMessages([
                    'clientResponse' => 'Mancano lo slot selezionato o il cliente collegato.',
                ]);
            }

            $timezone = config('app.timezone');
            $startAt = Carbon::parse(
                $slot->date->format('Y-m-d').' '.$slot->starts_at,
                $timezone
            );
            $endAt = Carbon::parse(
                $slot->date->format('Y-m-d').' '.$slot->ends_at,
                $timezone
            );
            $contextName = $shoot->project?->name
                ?? $shoot->marketingCampaign?->name
                ?? $shoot->title;

            $event = CalendarEvent::create([
                'client_id' => $client->id,
                'project_id' => $shoot->project_id,
                'created_by' => $actorId,
                'assigned_to' => $shoot->photographer_id,
                'title' => 'Shooting: '.$contextName,
                'description' => implode("\n", array_filter([
                    'Shooting confermato dal cliente.',
                    $shoot->client_notes ? "Note cliente: {$shoot->client_notes}" : null,
                    $shoot->internal_notes ? "Note interne: {$shoot->internal_notes}" : null,
                ])),
                'type' => 'other',
                'status' => 'scheduled',
                'start_at' => $startAt,
                'end_at' => $endAt,
                'is_all_day' => false,
                'location' => $shoot->location,
            ]);

            $task = Task::create([
                'project_id' => $shoot->project_id,
                'created_by' => $actorId,
                'assigned_to' => $shoot->photographer_id,
                'title' => 'Shooting: '.$contextName,
                'description' => implode("\n", array_filter([
                    'Effettuare lo shooting confermato.',
                    "Data: {$slot->date->format('d/m/Y')} ({$slot->starts_at} - {$slot->ends_at})",
                    $shoot->location ? "Luogo: {$shoot->location}" : null,
                    $shoot->client_notes ? "Indicazioni cliente: {$shoot->client_notes}" : null,
                    $shoot->internal_notes ? "Note interne: {$shoot->internal_notes}" : null,
                ])),
                'status' => 'todo',
                'priority' => 'high',
                'due_date' => $slot->date,
            ]);

            $shoot->update([
                'status' => ShootStatus::Scheduled,
                'client_confirmation_status' => 'accepted',
                'client_confirmed_at' => now(),
                'calendar_event_id' => $event->id,
                'task_id' => $task->id,
            ]);

            $this->notifyAll(
                $shoot,
                ShootingWorkflowEvent::ClientConfirmed,
                'Shooting confermato',
                "Il cliente ha confermato lo shooting {$shoot->code}. Calendario e task sono stati aggiornati."
            );
        });
    }

    private function notifyAll(
        Shoot $shoot,
        ShootingWorkflowEvent $event,
        string $title,
        string $message
    ): void {
        $usersToNotify = User::query()
            ->where('role', 'admin')
            ->orWhere('id', $shoot->created_by)
            ->orWhere('id', $shoot->photographer_id)
            ->get()
            ->unique('id');

        foreach ($usersToNotify as $user) {
            $url = ShootingRouteResolver::showRouteFor($user, $shoot);
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
