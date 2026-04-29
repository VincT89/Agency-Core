<?php

namespace App\Livewire\Client;

use App\Domain\Social\Actions\CreateOrUpdateClientSocialAccountAction;
use App\Models\Client;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialAccessMethod;
use App\Enums\Social\SocialAccessStatus;
use App\Enums\Social\SocialApiProvider;
use App\Enums\Social\SocialApiStatus;
use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ClientSocialAccountForm extends Component
{
    use AuthorizesRequests;

    public Client $client;
    
    public array $forms = [];
    public string $activeTab = 'facebook';

    public function mount(Client $client): void
    {
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
            'access_method' => $account?->access_method?->value ?? 'unknown',
            'access_status' => $account?->access_status?->value ?? 'not_started',
            'is_ready_to_publish' => $account?->is_ready_to_publish ?? false,
            'business_manager_id' => $account?->business_manager_id ?? '',
            'business_center_id' => $account?->business_center_id ?? '',
            'tiktok_account_id' => $account?->tiktok_account_id ?? '',
            'credential_location' => $account?->credential_location ?? '',
            'api_provider' => $account?->api_provider?->value ?? '',
            'api_status' => $account?->api_status?->value ?? 'not_configured',
            'notes' => $account?->notes ?? '',
            'api_notes' => $account?->api_notes ?? '',
        ];
    }

    public function save(string $platform, CreateOrUpdateClientSocialAccountAction $action)
    {
        $this->authorize('update', $this->client);
        
        if (!isset($this->forms[$platform])) {
            return;
        }

        $data = $this->forms[$platform];
        
        // Normalizza e formatta l'URL prima della validazione
        if (!empty($data['account_url']) && !preg_match('~^(?:f|ht)tps?://~i', $data['account_url'])) {
            $data['account_url'] = 'https://' . $data['account_url'];
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
            ucfirst($platform) . ' salvato correttamente.'
        );

        $this->dispatch('client-social-accounts-updated');
    }

    public function render()
    {
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
        ]);
    }
}
