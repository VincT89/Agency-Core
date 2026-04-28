<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Client;

class ClientSocialOverview extends Component
{
    public Client $client;

    public function mount(Client $client)
    {
        $this->client = $client;
    }

    #[On('client-social-accounts-updated')]
    public function refreshOverview()
    {
        $this->client->refresh();
    }

    public function render()
    {
        return view('livewire.client.client-social-overview');
    }
}
