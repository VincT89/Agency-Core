<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class NextcloudMediaTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    public function test_nextcloud_prevents_path_traversal_with_dot_dot_slash(): void
    {
        $user = \App\Models\User::factory()->create();
        
        $this->actingAs($user)
            ->get('/nextcloud/download?path=../etc/passwd')
            ->assertStatus(400);

        $this->actingAs($user)
            ->get('/nextcloud/preview?path=../etc/passwd')
            ->assertStatus(400);
            
        $this->actingAs($user)
            ->get('/media/marketing-campaign-posts/../etc/passwd')
            ->assertStatus(404); // Route constraints abort_if(str_contains($path, '..'), 404)
    }

    public function test_nextcloud_prevents_backslash_in_paths(): void
    {
        $user = \App\Models\User::factory()->create();
            
        $this->actingAs($user)
            ->get('/nextcloud/download?path=..\..\etc\passwd')
            ->assertStatus(400); // normalized to ../../etc/passwd -> 400

        // Symfony Request rejects backslash in URI path before reaching the application
        $this->expectException(\Symfony\Component\HttpFoundation\Exception\BadRequestException::class);
        $this->actingAs($user)
            ->get('/media/marketing-campaign-posts/something\..\else.jpg');
    }

    public function test_nextcloud_rejects_unallowed_mime_types(): void
    {
        // Media route aborts unless allowed mime type
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('marketing/campaign-posts/test.txt', 'Hello world');

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)
            ->get('/media/marketing-campaign-posts/test.txt')
            ->assertStatus(404); // Not allowed extension/mime
    }

    public function test_nextcloud_rejects_unallowed_file_extensions(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('marketing/campaign-posts/test.php', '<?php echo "evil";');

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)
            ->get('/media/marketing-campaign-posts/test.php')
            ->assertStatus(404);
    }

    public function test_nextcloud_returns_404_for_non_existent_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)
            ->get('/media/marketing-campaign-posts/non_existent.jpg')
            ->assertStatus(404);
    }

    public function test_nextcloud_prevents_access_to_paths_outside_permitted_root(): void
    {
        $user = \App\Models\User::factory()->create();
        
        $this->actingAs($user)
            ->get('/nextcloud/download?path=/SomeRandomDir/file.jpg')
            ->assertStatus(403);
            
        $this->actingAs($user)
            ->get('/nextcloud/preview?path=/SomeRandomDir/file.jpg')
            ->assertStatus(403);
    }

    public function test_media_signed_url_access_fails_if_expired(): void
    {
        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'disk' => 'public',
            'path' => 'test.jpg'
        ]);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'social.media.delivery',
            now()->subMinutes(10), // expired
            ['media' => $media->id]
        );

        $this->get($url)->assertStatus(403);
    }

    public function test_media_signed_url_access_fails_if_signature_is_modified(): void
    {
        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'disk' => 'public',
            'path' => 'test.jpg'
        ]);

        $url = \Illuminate\Support\Facades\URL::signedRoute(
            'social.media.delivery',
            ['media' => $media->id]
        );

        $modifiedUrl = $url . '1';

        $this->get($modifiedUrl)->assertStatus(403);
    }

    public function test_private_media_is_not_accessible_without_authorization(): void
    {
        // For Nextcloud download/preview
        $this->get('/nextcloud/download?path=/FotoClienti/test.jpg')->assertRedirect('/login');
        $this->get('/nextcloud/preview?path=/FotoClienti/test.jpg')->assertRedirect('/login');
    }

    public function test_public_media_is_accessible(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        
        // Let's create a valid image mock
        // Since we mock, mime_content_type and mimeType might fail if it's not a real image.
        // We can just skip actual image verification by mocking the mimeType response or use an actual 1px image.
        // To keep it simple, we just create it. But wait, Route uses \Illuminate\Support\Facades\Storage::disk('public')->mimeType($fullPath).
        \Illuminate\Support\Facades\Storage::disk('public')->put('clients/logos/logo.jpg', 'fake image data');
        
        // This public media path only allows clients/logos/
        $this->get('/media/clients/logos/logo.jpg')
            ->assertStatus(200);
    }
}
