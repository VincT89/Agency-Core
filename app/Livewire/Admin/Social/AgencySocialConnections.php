<?php

namespace App\Livewire\Admin\Social;

use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Domain\Social\Actions\SyncMetaAssetsAction;
use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialApiStatus;
use App\Models\AgencySocialConnection;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class AgencySocialConnections extends Component
{
    public function syncConnection($connectionId)
    {
        $this->authorize('manage_social_operations');
        $connection = AgencySocialConnection::findOrFail($connectionId);

        try {
            $action = app(SyncMetaAssetsAction::class);
            $result = $action->execute($connection);

            if ($result->isSuccessful()) {
                session()->flash('success', "Sincronizzazione completata: {$result->newCreated} nuovi, {$result->updated} aggiornati, {$result->revoked} rimossi.");
            } else {
                Log::warning('Agency social connection sync returned an error', [
                    'connection_id' => $connectionId,
                    'error' => $result->errorMessage,
                ]);
                session()->flash('error', 'Non è stato possibile aggiornare gli account collegati. Riprova tra poco.');
            }
        } catch (\Exception $e) {
            Log::error('AgencySocialConnections sync error', ['error' => $e->getMessage()]);
            session()->flash('error', 'Errore imprevisto durante la sincronizzazione.');
        }
    }

    public function revokeConnection(int $connectionId): void
    {
        $this->authorize('manage_social_operations');
        $connection = AgencySocialConnection::findOrFail($connectionId);

        DB::transaction(function () use ($connection) {
            $assetIds = $connection->assets()->pluck('id')->toArray();

            if (! empty($assetIds)) {
                $clientAccounts = ClientSocialAccount::whereIn('agency_social_asset_id', $assetIds)->get();
                $clientAccountIds = $clientAccounts->pluck('id')->toArray();

                if (! empty($clientAccountIds)) {
                    $publicationsToFail = MarketingCampaignPostPublication::whereIn('client_social_account_id', $clientAccountIds)
                        ->whereIn('status', [
                            PublicationStatus::Pending->value,
                            PublicationStatus::Publishing->value,
                        ])->get();

                    if ($publicationsToFail->isNotEmpty()) {
                        MarketingCampaignPostPublication::whereIn('id', $publicationsToFail->pluck('id'))
                            ->update([
                                'status' => PublicationStatus::Failed->value,
                                'error_message' => 'Pubblicazione annullata: la connessione agenzia Meta è stata revocata.',
                            ]);

                        $postIds = $publicationsToFail->pluck('marketing_campaign_post_id')->unique();
                        foreach ($postIds as $postId) {
                            $post = MarketingCampaignPost::find($postId);
                            if ($post) {
                                app(SyncMarketingCampaignPostPublicationStatusAction::class)->execute($post);
                            }
                        }
                    }
                }

                ClientSocialAccount::whereIn('agency_social_asset_id', $assetIds)->update([
                    'agency_social_asset_id' => null,
                    'api_status' => SocialApiStatus::NotConfigured,
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
                'status' => AgencyConnectionStatus::Revoked,
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
        ])->layout('layouts.app', ['title' => 'Connessioni Social']);
    }
}
