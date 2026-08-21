<?php

namespace Tests\Feature\Seeders;

use App\Enums\Finance\ElectronicInvoiceTransmissionStatus;
use App\Enums\UserRole;
use App\Models\AgencySocialAsset;
use App\Models\AgencySocialConnection;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\ElectronicInvoiceTransmission;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use App\Models\User;
use App\Models\UserAvailability;
use Database\Seeders\PurgeDemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeDemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    public function test_it_preserves_only_one_admin_while_deleting_all_application_data(): void
    {
        $admin = $this->admin(['remember_token' => 'previous-remember-token']);
        $otherUser = User::factory()->create(['role' => UserRole::Marketing]);
        UserAvailability::factory()->for($otherUser)->create([
            'date' => today()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '14:00:00',
        ]);
        $billingProfile = $this->billingProfile();
        InvoiceNumberSequence::create([
            'billing_profile_id' => $billingProfile->getKey(),
            'year' => 2026,
            'series' => 'FE',
            'next_number' => 12,
        ]);

        $connection = AgencySocialConnection::create([
            'provider' => 'facebook',
            'provider_user_id' => 'agency-user',
            'access_token' => 'encrypted-by-model',
            'connected_by' => $otherUser->getKey(),
        ]);
        $asset = AgencySocialAsset::create([
            'agency_social_connection_id' => $connection->getKey(),
            'provider' => 'facebook',
            'platform' => 'facebook',
            'asset_type' => 'facebook_page',
            'provider_asset_id' => 'page-1',
            'name' => 'Agency page',
        ]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create([
            'client_id' => $client->getKey(),
            'created_by' => $otherUser->getKey(),
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->getKey(),
            'created_by' => $otherUser->getKey(),
        ]);
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->getKey(),
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->getKey(),
            'created_by' => $otherUser->getKey(),
        ]);
        $version->mediaItems()->attach($media->getKey(), ['sort_order' => 0]);
        $post->update(['current_version_id' => $version->getKey()]);

        $clientSocialAccount = ClientSocialAccount::factory()->create([
            'client_id' => $client->getKey(),
            'agency_social_asset_id' => $asset->getKey(),
            'assignment_changed_by' => $otherUser->getKey(),
        ]);
        $firstPublication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->getKey(),
            'marketing_campaign_post_version_id' => $version->getKey(),
            'client_social_account_id' => $clientSocialAccount->getKey(),
        ]);
        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->getKey(),
            'marketing_campaign_post_version_id' => $version->getKey(),
            'client_social_account_id' => $clientSocialAccount->getKey(),
            'retry_of_publication_id' => $firstPublication->getKey(),
        ]);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'test-notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->getKey(),
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runSeeder()
            ->expectsOutputToContain('Pulizia completata')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'id' => $admin->getKey(),
            'email' => $admin->email,
            'role' => UserRole::Admin->value,
            'status' => 'active',
        ]);
        $this->assertNotSame(
            'previous-remember-token',
            DB::table('users')->where('id', $admin->getKey())->value('remember_token')
        );

        foreach ($this->applicationDataTables() as $table) {
            $this->assertSame(0, DB::table($table)->count(), "The {$table} table was not emptied.");
        }
    }

    public function test_it_runs_without_questions_and_keeps_the_first_active_admin(): void
    {
        $firstAdmin = $this->admin();
        $secondAdmin = $this->admin();
        User::factory()->create(['role' => UserRole::Developer]);

        $this->runSeeder()->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $firstAdmin->getKey()]);
        $this->assertDatabaseMissing('users', ['id' => $secondAdmin->getKey()]);
    }

    public function test_it_refuses_to_delete_a_real_aruba_transmission(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $invoice = Invoice::create([
            'client_id' => $client->getKey(),
            'created_by' => $admin->getKey(),
            'number' => 'TEST-001',
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'currency' => 'EUR',
        ]);
        $transmission = ElectronicInvoiceTransmission::create([
            'invoice_id' => $invoice->getKey(),
            'submitted_by' => $admin->getKey(),
            'provider' => 'aruba',
            'environment' => 'production',
            'mode' => 'live',
            'attempt_number' => 1,
            'status' => ElectronicInvoiceTransmissionStatus::Processing,
            'xml_filename' => 'IT01234567890_00001.xml',
            'xml_hash' => hash('sha256', '<xml/>'),
            'xml_content' => '<xml/>',
        ]);

        $this->runSeeder()
            ->expectsOutputToContain('sono presenti invii di fatture elettroniche')
            ->assertFailed();

        $this->assertModelExists($transmission);
        $this->assertModelExists($invoice);
    }

    public function test_it_refuses_an_unknown_table_instead_of_deleting_it(): void
    {
        $this->admin();
        Schema::create('future_operational_records', function ($table): void {
            $table->id();
        });

        $this->runSeeder()
            ->expectsOutputToContain('struttura del database non coincide')
            ->expectsOutputToContain('future_operational_records')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(Schema::hasTable('future_operational_records'));
    }

    private function runSeeder()
    {
        return $this->artisan('db:seed', [
            '--class' => PurgeDemoDataSeeder::class,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'status' => 'active',
            ...$attributes,
        ]);
    }

    private function billingProfile(): BillingProfile
    {
        return BillingProfile::create([
            'profile_key' => 'default',
            'legal_name' => 'Sodano Consulting',
            'vat_country_code' => 'IT',
            'vat_number' => '01234567890',
            'fiscal_regime' => 'RF01',
            'address' => 'Via Roma 1',
            'postal_code' => '70100',
            'city' => 'Bari',
            'province' => 'BA',
            'country_code' => 'IT',
        ]);
    }

    /**
     * @return list<string>
     */
    private function applicationDataTables(): array
    {
        return collect(Schema::getTableListing(Schema::getCurrentSchemaName(), false))
            ->reject(fn (string $table): bool => str_starts_with($table, 'sqlite_'))
            ->diff(['migrations', 'users'])
            ->values()
            ->all();
    }
}
