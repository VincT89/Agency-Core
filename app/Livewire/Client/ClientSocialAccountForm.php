<?php

namespace App\Livewire\Client;

use App\Domain\Social\Actions\CreateOrUpdateClientSocialAccountAction;
use App\Models\Client;
use App\Enums\Social\ClientSocialAccessStatus;
use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ClientSocialAccountForm extends Component
{
    use AuthorizesRequests;

    public Client $client;
    
    public string $facebook_page_url = '';
    public string $instagram_profile_url = '';
    public string $meta_business_manager_id = '';
    public bool $has_agency_access = false;
    public string $access_status = 'missing';
    public string $notes = '';

    public function mount(Client $client)
    {
        $this->client = $client;
        $account = $client->metaAccount;
        
        if ($account) {
            $this->facebook_page_url = $account->facebook_page_url ?? '';
            $this->instagram_profile_url = $account->instagram_profile_url ?? '';
            $this->meta_business_manager_id = $account->meta_business_manager_id ?? '';
            $this->has_agency_access = $account->has_agency_access ?? false;
            $this->access_status = $account->access_status?->value ?? 'missing';
            $this->notes = $account->notes ?? '';
        }
    }

    public function save(CreateOrUpdateClientSocialAccountAction $action)
    {
        $this->authorize('update', $this->client);
        
        $action->execute($this->client, [
            'provider' => 'meta',
            'facebook_page_url' => $this->facebook_page_url,
            'instagram_profile_url' => $this->instagram_profile_url,
            'meta_business_manager_id' => $this->meta_business_manager_id,
            'has_agency_access' => $this->has_agency_access,
            'access_status' => $this->access_status,
            'notes' => $this->notes,
        ]);

        session()->flash('success', 'Dati Meta salvati correttamente.');
    }

    public function render()
    {
        return view('livewire.client.client-social-account-form', [
            'statuses' => ClientSocialAccessStatus::cases()
        ]);
    }
}
