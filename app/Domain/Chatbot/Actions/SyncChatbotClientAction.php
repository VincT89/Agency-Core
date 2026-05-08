<?php

namespace App\Domain\Chatbot\Actions;

use App\Models\Client;
use App\Models\Chatbot\ChatbotClient;
use App\Models\HostingService;
use App\Services\Chatbot\PhoneNormalizer;
use App\Domain\Chatbot\Support\ChatbotLabelMapper;

class SyncChatbotClientAction
{
    public function __construct(private PhoneNormalizer $phoneNormalizer)
    {
    }

    public function execute(Client $client): ChatbotClient
    {
        // Assicuriamoci che normalized_phone esista in clients
        $normalizedPhone = $this->phoneNormalizer->normalize($client->phone);
        
        if ($client->normalized_phone !== $normalizedPhone) {
            // Aggiorna silenziosamente il cliente originale senza scatenare eventi ricorsivi
            $client->updateQuietly(['normalized_phone' => $normalizedPhone]);
        }

        $website = HostingService::query()
            ->where('client_id', $client->id)
            ->whereNotNull('domain')
            ->where('domain', '!=', '')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->value('domain');

        return ChatbotClient::updateOrCreate(
            ['client_id' => $client->id],
            [
                'name' => $client->name,
                'company_name' => $client->company_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'normalized_phone' => $normalizedPhone,
                'website' => $website,
                'status' => ChatbotLabelMapper::status($client->status),
                'activity_description' => $client->activity_description,
                'source_updated_at' => $client->updated_at,
                'synced_at' => now(),
            ]
        );
    }
}
