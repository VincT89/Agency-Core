<?php

namespace App\Livewire\Client;

use App\Domain\Social\Actions\CreateOrUpdateClientSocialAccountAction;
use App\Domain\Social\Actions\ValidateAgencyAssetAssignmentAction;
use App\Enums\Social\SocialAccessMethod;
use App\Enums\Social\SocialAccessStatus;
use App\Enums\Social\SocialApiProvider;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\AgencySocialAsset;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Support\Http\SocialProviderHttp;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ClientSocialAccountForm extends Component
{
    use AuthorizesRequests;

    public Client $client;

    public array $forms = [];

    public string $activeTab = 'facebook';

    public function mount(Client $client): void
    {
        $this->authorize('viewAny', ClientSocialAccount::class);
        $this->client = $client->load('socialAccounts');

        foreach (SocialPlatform::cases() as $platform) {
            $this->hydrateFormForPlatform($platform->value);
        }
    }

    private function hydrateFormForPlatform(string $platform): void
    {
        $this->client->load('socialAccounts');
        $account = $this->client->socialAccountFor($platform);

        $this->forms[$platform] = [
            'account_name' => $account?->account_name ?? '',
            'account_url' => $account?->account_url ?? '',
            'username' => $account?->username ?? '',
            'account_exists' => $account ? (string) (int) $account->account_exists : '0',
            'connection_mode' => $account?->connection_mode?->value ?? 'manual',
            'access_method' => $account?->access_method?->value ?? 'unknown',
            'access_status' => $account?->access_status?->value ?? 'not_started',
            'is_ready_to_publish' => $account?->is_ready_to_publish ?? false,
            'business_manager_id' => $account?->business_manager_id ?? '',
            'business_center_id' => $account?->business_center_id ?? '',
            'tiktok_account_id' => $account?->tiktok_account_id ?? '',
            'credential_location' => $account?->credential_location ?? '',
            'api_provider' => $account?->api_provider?->value ?? '',
            'api_status' => $account?->api_status?->value ?? 'not_configured',
            'provider_account_name' => $account?->provider_account_name ?? '',
            'notes' => $account?->notes ?? '',
            'api_notes' => $account?->api_notes ?? '',
            'agency_social_asset_id' => $account?->agency_social_asset_id ?? '',
            'connection_strategy' => $account?->connection_strategy?->value ?? ($platform === 'tiktok' ? 'platform_oauth' : 'agency_oauth'),
        ];
    }

    public function save(string $platform, CreateOrUpdateClientSocialAccountAction $action)
    {
        if (! isset($this->forms[$platform])) {
            return;
        }

        $account = $this->client->socialAccountFor($platform);
        $account
            ? $this->authorize('update', $account)
            : $this->authorize('create', ClientSocialAccount::class);

        $data = $this->forms[$platform];

        // Normalizza e formatta l'URL prima della validazione
        if (! empty($data['account_url']) && ! preg_match('~^(?:f|ht)tps?://~i', $data['account_url'])) {
            $data['account_url'] = 'https://'.$data['account_url'];
            $this->forms[$platform]['account_url'] = $data['account_url'];
        }

        // Valida i campi base
        $this->validate([
            "forms.$platform.account_url" => 'nullable|url',
        ], [
            "forms.$platform.account_url.url" => 'L\'URL inserito non è valido (es. https://...)',
        ]);

        $action->execute($this->client, $platform, $data);

        $this->client->refresh();
        $this->hydrateFormForPlatform($platform);

        session()->flash(
            'success_'.$platform,
            ucfirst($platform).' salvato correttamente.'
        );

        $this->dispatch('client-social-accounts-updated');
    }

    public function validateAssetAssignment(string $platform, int $assetId, ValidateAgencyAssetAssignmentAction $action)
    {
        $account = $this->client->socialAccountFor($platform);
        $account
            ? $this->authorize('update', $account)
            : $this->authorize('create', ClientSocialAccount::class);

        $asset = AgencySocialAsset::find($assetId);
        if ($asset) {
            $result = $action->execute($asset, $this->client->id, $platform);

            if ($result->isBlocked()) {
                $this->dispatch('show-toast', type: 'error', message: $result->message);
                $this->forms[$platform]['agency_social_asset_id'] = ''; // Reset selection
            } elseif ($result->isWarning()) {
                // Generiamo un warning blando senza bloccare l'UI
                $this->dispatch('show-toast', type: 'warning', message: $result->message);
            }
        }
    }

    public function disconnect(string $platform)
    {
        $account = $this->client->socialAccountFor($platform);
        if ($account) {
            $this->authorize('update', $account);

            $account->update([
                'agency_social_asset_id' => null,
                'api_status' => SocialApiStatus::NotConfigured,
                'connected_at' => null,
                'access_token' => null,
                'provider_account_id' => null,
                'provider_account_name' => null,
            ]);
            $this->hydrateFormForPlatform($platform);
            session()->flash('success_'.$platform, "Account {$platform} scollegato (Assegnazione rimossa).");
            $this->dispatch('client-social-accounts-updated');
        }
    }

    public function disconnectOauth(string $platform)
    {
        $account = $this->client->socialAccountFor($platform);
        if ($account) {
            $this->authorize('delete', $account);

            if ($platform === 'tiktok' && $account->access_token) {
                $apiBase = config('services.tiktok.api_base', 'https://open.tiktokapis.com');
                SocialProviderHttp::tiktok()->asForm()->post("{$apiBase}/v2/oauth/revoke/", [
                    'client_key' => config('services.tiktok.client_key'),
                    'client_secret' => config('services.tiktok.client_secret'),
                    'token' => $account->access_token,
                ]);
            }

            $account->update([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'tiktok_open_id' => null,
                'api_status' => SocialApiStatus::Disconnected,
                'account_exists' => false,
                'api_metadata' => null,
                'publishing_capabilities' => null,
            ]);
            $this->hydrateFormForPlatform($platform);
            session()->flash('success_'.$platform, "Connessione OAuth {$platform} revocata e scollegata.");
            $this->dispatch('client-social-accounts-updated');
        }
    }

    public function startTikTokOauth(string $platform)
    {
        $account = $this->client->socialAccountFor($platform);

        if (! $account || $platform !== 'tiktok') {
            session()->flash('error_'.$platform, 'Account non valido per questa operazione.');

            return;
        }

        $this->authorize('update', $account);

        // Salviamo dati di contesto estesi per validazione cross-tab
        session([
            'tiktok_oauth_account_id' => $account->id,
            'tiktok_oauth_client_id' => $this->client->id,
            'tiktok_oauth_expected_platform' => SocialPlatform::Tiktok->value,
        ]);

        return redirect()->route('admin.social.tiktok.redirect');
    }

    public function render()
    {
        $availableAssets = [];
        if ($this->activeTab === 'facebook' || $this->activeTab === 'instagram') {
            $platformType = $this->activeTab === 'facebook' ? 'facebook_page' : 'instagram_business_account';

            $query = AgencySocialAsset::where('asset_type', $platformType);

            if (! empty($this->forms[$this->activeTab]['agency_social_asset_id'])) {
                $selectedId = $this->forms[$this->activeTab]['agency_social_asset_id'];
                $query->where(function ($q) use ($selectedId) {
                    $q->where('is_active', true)
                        ->orWhere('id', $selectedId);
                });
            } else {
                $query->where('is_active', true);
            }

            $availableAssets = $query->get();
        }

        return view('livewire.client.client-social-account-form', [
            'platforms' => SocialPlatform::cases(),
            'existsOptions' => [
                '1' => 'Sì, Esiste',
                '0' => 'No, Da Creare',
            ],
            'accessMethods' => SocialAccessMethod::cases(),
            'accessStatuses' => SocialAccessStatus::cases(),
            'apiProviders' => SocialApiProvider::cases(),
            'apiStatuses' => SocialApiStatus::cases(),
            'availableAssets' => $availableAssets,
        ]);
    }
}
