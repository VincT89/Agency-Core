<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\ClientSocialAccountForm;
use App\Models\User;
use App\Models\Client;
use App\Enums\UserRole;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ClientSocialAccountFormTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => UserRole::Admin]);
        $this->client = Client::factory()->create();
    }

    public function test_it_renders_successfully()
    {
        Livewire::actingAs($this->user)
            ->test(ClientSocialAccountForm::class, ['client' => $this->client])
            ->assertStatus(200);
    }

    public function test_it_saves_facebook_data_and_persists()
    {
        Livewire::actingAs($this->user)
            ->test(ClientSocialAccountForm::class, ['client' => $this->client])
            ->set('forms.facebook.account_name', 'My FB Page')
            ->set('forms.facebook.account_url', 'https://facebook.com/myfbpage')
            ->set('forms.facebook.is_ready_to_publish', true)
            ->call('save', 'facebook')
            ->assertDispatched('client-social-accounts-updated');

        $this->assertDatabaseHas('client_social_accounts', [
            'client_id' => $this->client->id,
            'platform' => 'facebook',
            'account_name' => 'My FB Page',
            'account_url' => 'https://facebook.com/myfbpage',
            'is_ready_to_publish' => 1,
        ]);
    }
}
