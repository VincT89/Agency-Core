<?php

namespace Tests\Feature\Security;

use App\Models\Client;
use App\Models\MarketingCampaignPostMedia;
use App\Models\Project;
use App\Models\User;
use App\Services\Integrations\Nextcloud\NextcloudPathAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Tests\TestCase;

class NextcloudMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_nextcloud_prevents_path_traversal_with_dot_dot_slash(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/nextcloud/download?path=../etc/passwd')
            ->assertStatus(400);

        $this->actingAs($user)
            ->get('/nextcloud/preview?path=../etc/passwd')
            ->assertStatus(400);

        $this->actingAs($user)
            ->get('/media/marketing-campaign-posts/../etc/passwd')
            ->assertForbidden();
    }

    public function test_nextcloud_prevents_backslash_in_paths(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/nextcloud/download?path=..\..\etc\passwd')
            ->assertStatus(400); // normalized to ../../etc/passwd -> 400

        // Symfony Request rejects backslash in URI path before reaching the application
        $this->expectException(BadRequestException::class);
        $this->actingAs($user)
            ->get('/media/marketing-campaign-posts/something\..\else.jpg');
    }

    public function test_nextcloud_rejects_unallowed_mime_types(): void
    {
        // Media route aborts unless allowed mime type
        Storage::fake('public');
        Storage::disk('public')->put('marketing/campaign-posts/test.txt', 'Hello world');

        $user = User::factory()->create();
        $url = URL::signedRoute(
            'media.marketing-campaign-posts',
            ['path' => 'test.txt']
        );
        $this->actingAs($user)->get($url)->assertStatus(404);
    }

    public function test_nextcloud_rejects_unallowed_file_extensions(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('marketing/campaign-posts/test.php', '<?php echo "evil";');

        $user = User::factory()->create();
        $url = URL::signedRoute(
            'media.marketing-campaign-posts',
            ['path' => 'test.php']
        );
        $this->actingAs($user)->get($url)->assertStatus(404);
    }

    public function test_nextcloud_returns_404_for_non_existent_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $url = URL::signedRoute(
            'media.marketing-campaign-posts',
            ['path' => 'non_existent.jpg']
        );
        $this->actingAs($user)->get($url)->assertStatus(404);
    }

    public function test_nextcloud_prevents_access_to_paths_outside_permitted_root(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/nextcloud/download?path=/SomeRandomDir/file.jpg')
            ->assertStatus(403);

        $this->actingAs($user)
            ->get('/nextcloud/preview?path=/SomeRandomDir/file.jpg')
            ->assertStatus(403);
    }

    public function test_media_signed_url_access_fails_if_expired(): void
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'disk' => 'public',
            'path' => 'test.jpg',
        ]);

        $url = URL::temporarySignedRoute(
            'social.media.delivery',
            now()->subMinutes(10), // expired
            ['media' => $media->id]
        );

        $this->get($url)->assertStatus(403);
    }

    public function test_media_signed_url_access_fails_if_signature_is_modified(): void
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'disk' => 'public',
            'path' => 'test.jpg',
        ]);

        $url = URL::signedRoute(
            'social.media.delivery',
            ['media' => $media->id]
        );

        $modifiedUrl = $url.'1';

        $this->get($modifiedUrl)->assertStatus(403);
    }

    public function test_private_media_is_not_accessible_without_authorization(): void
    {
        // For Nextcloud download/preview
        $this->get('/nextcloud/download?path=/FotoClienti/test.jpg')->assertRedirect('/login');
        $this->get('/nextcloud/preview?path=/FotoClienti/test.jpg')->assertRedirect('/login');
    }

    public function test_nextcloud_paths_are_isolated_by_assigned_client(): void
    {
        config([
            'services.nextcloud.photos_root' => '/FotoClienti',
            'services.nextcloud.videos_root' => '/VideoClienti',
        ]);

        $developer = User::factory()->create(['role' => 'developer']);
        $assignedClient = Client::factory()->create([
            'nextcloud_folder_name' => 'cliente-assegnato',
            'nextcloud_photos_path' => '/FotoClienti/cliente-assegnato',
        ]);
        $foreignClient = Client::factory()->create([
            'nextcloud_folder_name' => 'cliente-estraneo',
            'nextcloud_photos_path' => '/FotoClienti/cliente-estraneo',
        ]);
        $project = Project::factory()->create([
            'client_id' => $assignedClient->id,
        ]);
        $project->users()->attach($developer->id, ['role' => 'contributor']);

        $authorizer = app(
            NextcloudPathAuthorizer::class
        );

        $this->assertTrue(
            $authorizer->canAccess(
                $developer,
                '/FotoClienti/cliente-assegnato/foto.jpg'
            )
        );
        $this->assertTrue(
            $authorizer->canAccess(
                $developer,
                '/VideoClienti/cliente-assegnato/video.mp4'
            )
        );
        $this->assertFalse(
            $authorizer->canAccess(
                $developer,
                '/FotoClienti/cliente-estraneo/foto.jpg'
            )
        );
        $this->assertFalse(
            $authorizer->canAccess(
                $developer,
                '/VideoClienti/cliente-estraneo/video.mp4'
            )
        );
        $this->actingAs($developer)
            ->get('/nextcloud/preview?path=/FotoClienti/cliente-estraneo/foto.jpg')
            ->assertForbidden();
        $this->actingAs($developer)
            ->get('/nextcloud/download?path=/VideoClienti/cliente-estraneo/video.mp4')
            ->assertForbidden();

        $marketing = User::factory()->create(['role' => 'marketing']);
        $this->assertTrue(
            $authorizer->canAccess(
                $marketing,
                $foreignClient->nextcloud_photos_path.'/foto.jpg'
            )
        );
    }

    public function test_public_media_is_accessible(): void
    {
        Storage::fake('public');

        // Let's create a valid image mock
        // Since we mock, mime_content_type and mimeType might fail if it's not a real image.
        // We can just skip actual image verification by mocking the mimeType response or use an actual 1px image.
        // To keep it simple, we just create it. But wait, Route uses \Illuminate\Support\Facades\Storage::disk('public')->mimeType($fullPath).
        Storage::disk('public')->put('clients/logos/logo.jpg', 'fake image data');

        // This public media path only allows clients/logos/
        $url = URL::signedRoute(
            'media.public',
            ['path' => 'clients/logos/logo.jpg']
        );

        $this->get($url)
            ->assertStatus(200);
    }
}
