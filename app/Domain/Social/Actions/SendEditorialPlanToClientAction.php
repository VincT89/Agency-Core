<?php

namespace App\Domain\Social\Actions;

use App\Models\EditorialPlan;
use App\Models\User;
use App\Enums\Social\EditorialPlanStatus;

class SendEditorialPlanToClientAction
{
    public function __construct(
        protected \App\Services\Integrations\N8n\N8nClient $n8nClient
    ) {}

    public function execute(EditorialPlan $plan, User $user): string
    {
        $plan->update(['status' => EditorialPlanStatus::SentToClient->value]);
        $plan->marketingProject->update(['status' => \App\Enums\Social\MarketingProjectStatus::SentToClient->value]);
        $plan->slots()->update(['status' => \App\Enums\Social\EditorialPlanSlotStatus::SentToClient->value]);

        $tokenString = \Illuminate\Support\Str::random(40);
        
        \App\Models\ClientReviewToken::create([
            'reviewable_id' => $plan->id,
            'reviewable_type' => EditorialPlan::class,
            'token' => $tokenString,
            'expires_at' => now()->addDays(30),
        ]);

        $link = route('client.review', ['token' => $tokenString]);

        // Invia notifica WhatsApp tramite n8n
        try {
            $this->n8nClient->sendWhatsappReviewLink([
                'client_name' => $plan->marketingProject->client->name ?? 'Cliente',
                'client_phone' => $plan->marketingProject->client->phone ?? '', // Assicurati di avere il campo nel DB
                'project_title' => $plan->marketingProject->title,
                'review_link' => $link,
                'type' => 'editorial_plan',
            ]);
        } catch (\Exception $e) {
            // Log in addition to n8n logging if needed, but not failing the transaction
            \Illuminate\Support\Facades\Log::error('Impossibile inviare notifica WhatsApp: ' . $e->getMessage());
        }

        return $link;
    }
}
