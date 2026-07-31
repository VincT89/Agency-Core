<?php

namespace App\Domain\Shooting\Services;

use App\Models\Client;
use App\Models\Shooting\Shoot;
use App\Services\Chatbot\PhoneNormalizer;

class ShootingClientCommunicationService
{
    public function __construct(private readonly PhoneNormalizer $phoneNormalizer)
    {
    }

    /**
     * @return array{
     *     client: ?Client,
     *     message: string,
     *     email_url: ?string,
     *     whatsapp_url: ?string
     * }
     */
    public function for(Shoot $shoot): array
    {
        $shoot->loadMissing([
            'project.client',
            'marketingCampaign.client',
            'selectedSlot',
        ]);

        $client = $shoot->clientRecord();
        $slot = $shoot->selectedSlot;
        $clientName = $client?->reference_person ?: $client?->name ?: 'Cliente';
        $date = $slot?->date?->format('d/m/Y') ?? 'da concordare';
        $time = $slot
            ? substr((string) $slot->starts_at, 0, 5).' - '.substr((string) $slot->ends_at, 0, 5)
            : 'da concordare';

        $message = implode("\n", array_filter([
            "Buongiorno {$clientName},",
            '',
            "il fotografo ha confermato la propria disponibilità per lo shooting \"{$shoot->title}\".",
            "Data proposta: {$date}",
            "Orario: {$time}",
            $shoot->location ? "Luogo: {$shoot->location}" : null,
            $shoot->client_notes ? "Indicazioni: {$shoot->client_notes}" : null,
            '',
            'Può confermarci la disponibilità oppure indicarci se è necessario proporre una nuova data?',
        ], static fn ($line) => $line !== null));

        $emailUrl = null;
        if ($client?->email) {
            $emailUrl = 'mailto:'.$client->email
                .'?subject='.rawurlencode('Conferma shooting '.$shoot->title)
                .'&body='.rawurlencode($message);
        }

        // Il numero originale evita di propagare eventuali normalizzazioni
        // storiche errate; il fallback copre i record che hanno solo il dato pulito.
        $phone = $client?->phone ?: $client?->normalized_phone;
        $whatsappNumber = $this->phoneNormalizer->normalize($phone);
        $whatsappUrl = $whatsappNumber
            ? 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode($message)
            : null;

        return [
            'client' => $client,
            'message' => $message,
            'email_url' => $emailUrl,
            'whatsapp_url' => $whatsappUrl,
        ];
    }
}
