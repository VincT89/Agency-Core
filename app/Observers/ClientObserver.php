<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\AuditLogService;

class ClientObserver
{
    public function saving(Client $client): void
    {
        $phoneFields = ['phone', 'mobile', 'whatsapp'];

        foreach ($phoneFields as $field) {
            if (array_key_exists($field, $client->getAttributes()) && $client->isDirty($field)) {
                if (!empty($client->{$field})) {
                    $client->normalized_phone = app(\App\Services\Chatbot\PhoneNormalizer::class)->normalize($client->{$field});
                } else {
                    $client->normalized_phone = null;
                }
                break; // Use the first available updated phone field for the normalized output
            }
        }
    }

    public function saved(Client $client): void
    {
        if ($client->wasChanged(['name', 'company_name', 'email', 'phone', 'status', 'activity_description'])) {
            \App\Jobs\Chatbot\SyncChatbotClientDataJob::dispatch($client->id)
                ->delay(now()->addSeconds(10))
                ->onQueue('chatbot');
        }
    }

    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Client $client): void
    {
        $this->auditLog->log('created', $client, null, $client->getAttributes());
    }

    public function updated(Client $client): void
    {
        $old = array_intersect_key($client->getOriginal(), $client->getDirty());
        $new = $client->getDirty();

        $action = isset($new['status']) ? 'status_changed' : 'updated';

        $this->auditLog->log($action, $client, $old, $new);
    }

    public function deleted(Client $client): void
    {
        $this->auditLog->log('deleted', $client, $client->getOriginal(), null);
    }
}
