<?php

namespace App\Livewire\Admin\Social;

use Livewire\Component;
use App\Models\AgencySocialConnection;
use App\Domain\Social\Actions\SyncMetaAssetsAction;
use Illuminate\Support\Facades\Log;

class AgencySocialConnections extends Component
{
    public function syncConnection($connectionId)
    {
        $connection = AgencySocialConnection::findOrFail($connectionId);

        try {
            $action = app(SyncMetaAssetsAction::class);
            $result = $action->execute($connection);

            if ($result->isSuccessful()) {
                session()->flash('success', "Sincronizzazione completata: {$result->newCreated} nuovi, {$result->updated} aggiornati, {$result->revoked} rimossi.");
            } else {
                session()->flash('error', "Errore durante la sincronizzazione: {$result->errorMessage}");
            }
        } catch (\Exception $e) {
            Log::error('AgencySocialConnections sync error', ['error' => $e->getMessage()]);
            session()->flash('error', 'Errore imprevisto durante la sincronizzazione.');
        }
    }

    public function revokeConnection(int $connectionId): void
    {
        $connection = AgencySocialConnection::findOrFail($connectionId);

        \Illuminate\Support\Facades\DB::transaction(function () use ($connection) {
            $assetIds = $connection->assets()->pluck('id')->toArray();
            
            if (!empty($assetIds)) {
                $clientAccounts = \App\Models\ClientSocialAccount::whereIn('agency_social_asset_id', $assetIds)->get();
                $clientAccountIds = $clientAccounts->pluck('id')->toArray();
                
                if (!empty($clientAccountIds)) {
                    $publicationsToFail = \App\Models\MarketingCampaignPostPublication::whereIn('client_social_account_id', $clientAccountIds)
                        ->whereIn('status', [
                            \App\Enums\Social\PublicationStatus::Pending->value, 
                            \App\Enums\Social\PublicationStatus::Publishing->value
                        ])->get();

                    if ($publicationsToFail->isNotEmpty()) {
                        \App\Models\MarketingCampaignPostPublication::whereIn('id', $publicationsToFail->pluck('id'))
                            ->update([
                                'status' => \App\Enums\Social\PublicationStatus::Failed->value,
                                'error_message' => 'Pubblicazione annullata: la connessione agenzia Meta è stata revocata.',
                            ]);
                            
                        $postIds = $publicationsToFail->pluck('marketing_campaign_post_id')->unique();
                        foreach ($postIds as $postId) {
                            $post = \App\Models\MarketingCampaignPost::find($postId);
                            if ($post) {
                                app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($post);
                            }
                        }
                    }
                }

                \App\Models\ClientSocialAccount::whereIn('agency_social_asset_id', $assetIds)->update([
                    'agency_social_asset_id' => null,
                    'api_status' => \App\Enums\Social\SocialApiStatus::NotConfigured,
                    'is_ready_to_publish' => false,
                    'last_api_error' => 'Connessione agenzia Meta revocata.',
                    'connected_at' => null,
                    'access_token' => null,
                ]);
            }

            $connection->assets()->update([
                'is_active' => false,
                'is_assignable' => false,
                'revoked_at' => now(),
            ]);

            $connection->update([
                'status' => \App\Enums\Social\AgencyConnectionStatus::Revoked,
                'requires_reauth' => true,
                'last_api_error' => 'Connessione revocata manualmente da pannello admin.',
            ]);
        });

        session()->flash('success', 'Connessione Meta revocata. Gli asset collegati sono stati disattivati.');
    }

    public function render()
    {
        return view('livewire.admin.social.agency-social-connections', [
            'connections' => AgencySocialConnection::with('assets', 'connectedBy')->get(),
        ])->layout('layouts.app');
    }
}
