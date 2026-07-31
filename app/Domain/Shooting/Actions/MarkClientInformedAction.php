<?php

namespace App\Domain\Shooting\Actions;

use App\Enums\Shooting\ShootClientContactChannel;
use App\Enums\Shooting\ShootingWorkflowEvent;
use App\Enums\Shooting\ShootStatus;
use App\Helpers\ShootingRouteResolver;
use App\Models\Shooting\Shoot;
use App\Models\User;
use App\Notifications\ShootingWorkflowNotification;
use App\Services\Chatbot\PhoneNormalizer;
use Illuminate\Validation\ValidationException;

class MarkClientInformedAction
{
    public function __construct(private readonly PhoneNormalizer $phoneNormalizer)
    {
    }

    public function execute(Shoot $shoot, ShootClientContactChannel $channel): void
    {
        if ($shoot->status !== ShootStatus::WaitingClient) {
            throw ValidationException::withMessages([
                'clientChannel' => 'Il cliente può essere informato soltanto dopo la conferma del fotografo.',
            ]);
        }

        $shoot->loadMissing(['project.client', 'marketingCampaign.client']);
        $client = $shoot->clientRecord();

        if (! $client) {
            throw ValidationException::withMessages([
                'clientChannel' => 'Non è stato possibile individuare il cliente collegato allo shooting.',
            ]);
        }

        $recipient = match ($channel) {
            ShootClientContactChannel::Email => $client->email,
            ShootClientContactChannel::Whatsapp,
            ShootClientContactChannel::Phone => $this->phoneNormalizer->normalize(
                $client->phone ?: $client->normalized_phone
            ),
            ShootClientContactChannel::Other => $client->reference_person ?: $client->name,
        };

        if (blank($recipient)) {
            throw ValidationException::withMessages([
                'clientChannel' => match ($channel) {
                    ShootClientContactChannel::Email => 'Il cliente non ha un indirizzo email disponibile.',
                    ShootClientContactChannel::Whatsapp,
                    ShootClientContactChannel::Phone => 'Il cliente non ha un numero di telefono disponibile.',
                    ShootClientContactChannel::Other => 'Indica un contatto valido nei dati del cliente.',
                },
            ]);
        }

        $shoot->update([
            'client_confirmation_status' => 'waiting_response',
            'client_confirmation_channel' => $channel->value,
            'client_notification_recipient' => $recipient,
            'client_notified_at' => now(),
        ]);

        $usersToNotify = User::query()
            ->where('role', 'admin')
            ->orWhere('id', $shoot->created_by)
            ->orWhere('id', $shoot->photographer_id)
            ->get()
            ->unique('id');

        foreach ($usersToNotify as $user) {
            $user->notify(new ShootingWorkflowNotification(
                ShootingWorkflowEvent::ClientInformed,
                'Cliente informato',
                "Il cliente dello shooting {$shoot->code} è stato contattato tramite {$channel->label()}.",
                ShootingRouteResolver::showRouteFor($user, $shoot),
                $shoot->id
            ));
        }
    }
}
