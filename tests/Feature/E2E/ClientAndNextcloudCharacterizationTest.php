<?php

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Models\Client;
use App\Enums\UserRole;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ClientAndNextcloudCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_creation_with_nextcloud_directory()
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => UserRole::Admin
        ]);

        $mockNextcloud = Mockery::mock(NextcloudService::class);
        $mockNextcloud->shouldReceive('mediaRoot')
            ->with('photo')
            ->andReturn('/Media/Photos/');
        
        $mockNextcloud->shouldReceive('ensureDirectoryExists')
            ->with('/Media/Photos/test-client-folder')
            ->andReturn(true);

        $this->app->instance(NextcloudService::class, $mockNextcloud);

        $response = $this->actingAs($admin)->post('/clients', [
            'name' => 'Test Client NC',
            'company_name' => 'Test Company Srl',
            'email' => 'test@client.com',
            'vat_number' => 'IT12345678901',
            'nextcloud_folder_name' => 'test-client-folder',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', [
            'name' => 'Test Client NC',
            'nextcloud_folder_name' => 'test-client-folder',
            'nextcloud_photos_path' => '/Media/Photos/test-client-folder',
        ]);
    }

    public function test_client_creation_fails_if_nextcloud_directory_creation_fails()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin
        ]);

        $mockNextcloud = Mockery::mock(NextcloudService::class);
        $mockNextcloud->shouldReceive('mediaRoot')
            ->with('photo')
            ->andReturn('/Media/Photos/');
        
        $mockNextcloud->shouldReceive('ensureDirectoryExists')
            ->with('/Media/Photos/fail-client-folder')
            ->andReturn(false);

        $this->app->instance(NextcloudService::class, $mockNextcloud);

        $response = $this->actingAs($admin)->post('/clients', [
            'name' => 'Fail Client NC',
            'company_name' => 'Fail Company Srl',
            'email' => 'fail@client.com',
            'vat_number' => 'IT09876543210',
            'nextcloud_folder_name' => 'fail-client-folder',
        ]);

        $response->assertSessionHasErrors('nextcloud_folder_name');
        $this->assertDatabaseMissing('clients', [
            'name' => 'Fail Client NC',
        ]);
    }
}
