<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\HostingService;
use App\Models\User;
use App\Services\RenewalSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HostingServiceMultiTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->client = Client::factory()->create();
    }

    public function test_legacy_single_type_payload_remains_supported(): void
    {
        $payload = $this->validPayload([
            'type' => 'domain',
            'domain' => 'legacy.example',
        ]);
        unset($payload['service_types']);

        $response = $this->actingAs($this->admin)
            ->post(route('hosting-services.store'), $payload);

        $response->assertRedirect(route('hosting-services.index', ['type' => 'domain']));

        $service = HostingService::query()->sole();

        $this->assertSame('domain', $service->type);
        $this->assertSame(['domain'], $service->service_types);
    }

    public function test_multiple_types_are_saved_as_one_renewal(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('hosting-services.store'), $this->validPayload([
                'service_types' => ['email', 'domain', 'hosting'],
                'domain' => 'bundle.example',
                'renewal_date' => now()->addDays(10)->toDateString(),
                'context' => 'hosting',
            ]));

        $response->assertRedirect(route('hosting-services.index', ['exclude_type' => 'domain']));

        $service = HostingService::query()->sole();

        $this->assertSame(['domain', 'hosting', 'email'], $service->service_types);
        $this->assertSame('domain', $service->type);
        $this->assertDatabaseCount('hosting_services', 1);
        $this->assertSame(1, app(RenewalSummaryService::class)->getExpiringCount());
    }

    public function test_domain_is_required_only_when_domain_type_is_selected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('hosting-services.store'), $this->validPayload([
                'service_types' => ['domain', 'hosting'],
                'domain' => null,
            ]))
            ->assertSessionHasErrors(['domain']);

        $this->actingAs($this->admin)
            ->post(route('hosting-services.store'), $this->validPayload([
                'service_types' => ['hosting', 'email'],
                'domain' => null,
            ]))
            ->assertSessionDoesntHaveErrors(['domain']);

        $this->assertDatabaseCount('hosting_services', 1);
    }

    public function test_service_types_must_be_a_non_empty_known_set(): void
    {
        $this->actingAs($this->admin)
            ->post(route('hosting-services.store'), $this->validPayload([
                'service_types' => [],
            ]))
            ->assertSessionHasErrors(['service_types']);

        $this->actingAs($this->admin)
            ->post(route('hosting-services.store'), $this->validPayload([
                'service_types' => ['hosting', 'unknown'],
            ]))
            ->assertSessionHasErrors(['service_types.1']);

        $this->actingAs($this->admin)
            ->post(route('hosting-services.store'), $this->validPayload([
                'service_types' => ['hosting', 'hosting'],
            ]))
            ->assertSessionHasErrors(['service_types.0', 'service_types.1']);
    }

    public function test_domain_and_hosting_contexts_include_the_same_bundle_once(): void
    {
        $bundle = $this->createService('Pacchetto completo', ['domain', 'hosting', 'email']);
        $domainOnly = $this->createService('Solo dominio', ['domain']);
        $emailOnly = $this->createService('Solo email', ['email']);

        $domainResults = HostingService::query()->withServiceType('domain')->pluck('id');
        $hostingResults = HostingService::query()->withAnyServiceTypeExcept('domain')->pluck('id');

        $this->assertEqualsCanonicalizing([$bundle->id, $domainOnly->id], $domainResults->all());
        $this->assertEqualsCanonicalizing([$bundle->id, $emailOnly->id], $hostingResults->all());
        $this->assertSame(1, $hostingResults->filter(fn (int $id): bool => $id === $bundle->id)->count());

        $this->actingAs($this->admin)
            ->get(route('hosting-services.index', ['type' => 'domain']))
            ->assertOk()
            ->assertSeeText('Pacchetto completo')
            ->assertSeeText('Solo dominio')
            ->assertDontSeeText('Solo email');

        $this->actingAs($this->admin)
            ->get(route('hosting-services.index', ['exclude_type' => 'domain']))
            ->assertOk()
            ->assertSeeText('Pacchetto completo')
            ->assertSeeText('Solo email')
            ->assertDontSeeText('Solo dominio');
    }

    public function test_index_exposes_one_responsive_card_structure_per_renewal(): void
    {
        $this->createService('Pacchetto responsive', ['domain', 'hosting', 'email']);

        $response = $this->actingAs($this->admin)
            ->get(route('hosting-services.index', ['exclude_type' => 'domain']))
            ->assertOk()
            ->assertSee('hosting-services-table', false)
            ->assertSee('hosting-service-row', false)
            ->assertSee('data-label="Servizio"', false)
            ->assertSee('data-label="Credenziali"', false)
            ->assertSee('hosting-service-edit-label', false);

        $this->assertSame(1, substr_count($response->getContent(), 'Pacchetto responsive'));
    }

    public function test_existing_service_can_be_updated_to_multiple_types(): void
    {
        $service = $this->createService('Rinnovo esistente', ['hosting']);

        $response = $this->actingAs($this->admin)
            ->put(route('hosting-services.update', $service), $this->validPayload([
                'name' => $service->name,
                'service_types' => ['domain', 'hosting', 'email'],
                'domain' => 'updated.example',
                'context' => 'domain',
            ]));

        $response->assertRedirect(route('hosting-services.index', ['type' => 'domain']));

        $service->refresh();

        $this->assertSame(['domain', 'hosting', 'email'], $service->service_types);
        $this->assertSame('domain', $service->type);
    }

    public function test_redirect_context_never_hides_the_saved_service(): void
    {
        $this->actingAs($this->admin)
            ->post(route('hosting-services.store'), $this->validPayload([
                'name' => 'Solo hosting da domini',
                'service_types' => ['hosting'],
                'context' => 'domain',
            ]))
            ->assertRedirect(route('hosting-services.index', ['exclude_type' => 'domain']));

        $this->actingAs($this->admin)
            ->post(route('hosting-services.store'), $this->validPayload([
                'name' => 'Solo dominio da hosting',
                'service_types' => ['domain'],
                'domain' => 'domain-only.example',
                'context' => 'hosting',
            ]))
            ->assertRedirect(route('hosting-services.index', ['type' => 'domain']));

        $this->assertDatabaseCount('hosting_services', 2);
    }

    public function test_legacy_database_rows_are_still_readable_and_filterable(): void
    {
        $id = DB::table('hosting_services')->insertGetId([
            'client_id' => $this->client->id,
            'type' => 'domain',
            'service_types' => null,
            'name' => 'Record precedente',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = HostingService::query()->findOrFail($id);

        $this->assertSame(['domain'], $service->resolved_service_types);
        $this->assertTrue(
            HostingService::query()->withServiceType('domain')->whereKey($id)->exists()
        );
    }

    public function test_migration_backfills_existing_scalar_types(): void
    {
        $migration = require database_path('migrations/2026_09_01_090000_add_service_types_to_hosting_services_table.php');
        $migration->down();

        $id = DB::table('hosting_services')->insertGetId([
            'client_id' => $this->client->id,
            'type' => 'hosting',
            'name' => 'Record da migrare',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $storedTypes = DB::table('hosting_services')->where('id', $id)->value('service_types');

        $this->assertSame(['hosting'], json_decode($storedTypes, true, flags: JSON_THROW_ON_ERROR));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'client_id' => $this->client->id,
            'service_types' => ['hosting'],
            'name' => 'Rinnovo annuale',
            'status' => 'active',
            'provider' => 'Provider Test',
            'billing_cycle' => 'yearly',
        ], $overrides);
    }

    private function createService(string $name, array $serviceTypes): HostingService
    {
        return HostingService::create([
            'client_id' => $this->client->id,
            'type' => $serviceTypes[0],
            'service_types' => $serviceTypes,
            'name' => $name,
            'domain' => in_array('domain', $serviceTypes, true) ? str($name)->slug().'.example' : null,
            'status' => 'active',
        ]);
    }
}
