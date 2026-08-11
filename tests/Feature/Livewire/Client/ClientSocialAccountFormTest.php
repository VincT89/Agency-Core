<?php

namespace Tests\Feature\Livewire\Client;

use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\Social\PublishingStatus;
use App\Enums\Social\SocialAccessStatus;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialAssetType;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialPlatform;
use App\Enums\UserRole;
use App\Livewire\Client\ClientSocialAccountForm;
use App\Models\AgencySocialAsset;
use App\Models\AgencySocialConnection;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

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

    public function test_connected_tiktok_shows_connection_state_without_legacy_profile_fields(): void
    {
        $this->client->socialAccounts()->create([
            'platform' => SocialPlatform::Tiktok,
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'tiktok-access-token',
            'account_name' => 'test.r4',
            'account_exists' => true,
            'access_status' => SocialAccessStatus::NotStarted,
            'scopes' => ['user.info.basic', 'video.upload'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_upload_video_draft' => true,
                    'can_publish_video' => true,
                ],
            ],
        ]);

        Livewire::actingAs($this->user)
            ->test(ClientSocialAccountForm::class, ['client' => $this->client])
            ->set('activeTab', SocialPlatform::Tiktok->value)
            ->assertSee('Account TikTok collegato')
            ->assertSee('test.r4')
            ->assertSee('Bozze abilitate')
            ->assertSee('Salva note TikTok')
            ->assertSee('Scollega TikTok')
            ->assertDontSee('Il profilo esiste già?')
            ->assertDontSee('Link pubblico');
    }

    public function test_agency_meta_assignment_shows_the_assigned_asset_without_obsolete_warning(): void
    {
        $connection = AgencySocialConnection::forceCreate([
            'provider' => 'facebook',
            'provider_user_name' => 'Account Agenzia',
            'access_token' => 'agency-token',
            'status' => AgencyConnectionStatus::Connected,
            'requires_reauth' => false,
        ]);
        $asset = AgencySocialAsset::forceCreate([
            'agency_social_connection_id' => $connection->id,
            'provider' => 'facebook',
            'platform' => SocialPlatform::Facebook,
            'asset_type' => SocialAssetType::FacebookPage,
            'provider_asset_id' => 'page-123',
            'facebook_page_id' => 'page-123',
            'page_access_token' => 'page-token',
            'name' => 'Rullino test lab',
            'status' => AgencyConnectionStatus::Connected,
            'publishing_status' => PublishingStatus::Ready,
            'is_active' => true,
            'is_assignable' => true,
        ]);
        $this->client->socialAccounts()->create([
            'platform' => SocialPlatform::Facebook,
            'connection_strategy' => SocialConnectionStrategy::AgencyOauth,
            'agency_social_asset_id' => $asset->id,
            'api_status' => SocialApiStatus::Connected,
            'access_status' => SocialAccessStatus::NotStarted,
        ]);

        Livewire::actingAs($this->user)
            ->test(ClientSocialAccountForm::class, ['client' => $this->client])
            ->assertSee('Profilo Meta assegnato')
            ->assertSee('Rullino test lab')
            ->assertSee('Salva assegnazione')
            ->assertSee('Rimuovi assegnazione')
            ->assertDontSee('Richiede Meta Business Manager collegato')
            ->assertDontSee('Il profilo esiste già?');
    }

    public function test_marketing_user_cannot_revoke_tiktok_oauth_connection(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $account = $this->client->socialAccounts()->create([
            'platform' => SocialPlatform::Tiktok,
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'tiktok-access-token',
            'account_exists' => true,
            'access_status' => SocialAccessStatus::NotStarted,
        ]);

        Livewire::actingAs($marketing)
            ->test(ClientSocialAccountForm::class, ['client' => $this->client])
            ->call('disconnectOauth', SocialPlatform::Tiktok->value)
            ->assertForbidden();

        $this->assertNotNull($account->refresh()->access_token);
        $this->assertSame(SocialApiStatus::Connected, $account->api_status);
    }
}
