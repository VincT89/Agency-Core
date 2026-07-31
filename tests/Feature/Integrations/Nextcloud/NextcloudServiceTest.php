<?php

namespace Tests\Feature\Integrations\Nextcloud;

use App\Exceptions\NextcloudShareException;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class NextcloudServiceTest extends TestCase
{
    protected NextcloudService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.nextcloud.base_url' => 'https://nextcloud.test']);
        config(['services.nextcloud.username' => 'testuser']);
        config(['services.nextcloud.password' => 'secret']);
        config(['services.nextcloud.photos_root' => '/Photos']);
        config(['services.nextcloud.videos_root' => '/Videos']);

        $this->service = app(NextcloudService::class);
    }

    public function test_normalize_path_prevents_traversal()
    {
        $this->assertEquals('/Photos/test.jpg', $this->service->normalizePath('/Photos/test.jpg'));
        $this->assertEquals('/Photos/test.jpg', $this->service->normalizePath('\\Photos\\test.jpg'));
        $this->assertEquals('/test.jpg', $this->service->normalizePath('////test.jpg'));
    }

    public function test_normalize_path_aborts_on_dot_dot()
    {
        $this->expectException(HttpException::class);
        $this->service->normalizePath('/Photos/../secret.jpg');
    }

    public function test_normalize_path_aborts_on_suspicious_encoding()
    {
        $this->expectException(HttpException::class);
        // Doppio encode di spazio o altro carattere
        $this->service->normalizePath('/Photos/test%2520file.jpg');
    }

    public function test_invalid_base_url_scheme_is_not_configured()
    {
        config(['services.nextcloud.base_url' => 'file:///etc']);

        $this->assertFalse(app(NextcloudService::class)->isConfigured());
    }

    public function test_ensure_client_media_directories_provisions_photo_and_video_paths()
    {
        $service = \Mockery::mock(NextcloudService::class)->makePartial();
        $service->shouldReceive('ensureDirectoryExists')
            ->once()
            ->with('/Photos/acme')
            ->andReturnTrue();
        $service->shouldReceive('ensureDirectoryExists')
            ->once()
            ->with('/Videos/acme')
            ->andReturnTrue();

        $this->assertSame([
            'photo' => '/Photos/acme',
            'video' => '/Videos/acme',
        ], $service->ensureClientMediaDirectories('acme'));
    }

    public function test_ensure_client_media_directories_fails_if_one_directory_cannot_be_created()
    {
        $service = \Mockery::mock(NextcloudService::class)->makePartial();
        $service->shouldReceive('ensureDirectoryExists')
            ->once()
            ->with('/Photos/acme')
            ->andReturnTrue();
        $service->shouldReceive('ensureDirectoryExists')
            ->once()
            ->with('/Videos/acme')
            ->andReturnFalse();

        $this->assertNull(
            $service->ensureClientMediaDirectories('acme')
        );
    }

    public function test_list_files_handles_malformed_xml_gracefully()
    {
        Http::fake([
            '*' => Http::response('NOT XML AT ALL', 200),
        ]);

        $result = $this->service->listFiles('/Photos');

        $this->assertNull($result);
    }

    public function test_ensure_public_share_returns_existing_valid_share()
    {
        Http::fake([
            '*/ocs/v2.php/apps/files_sharing/api/v1/shares*' => Http::response([
                'ocs' => [
                    'meta' => ['statuscode' => 100],
                    'data' => [
                        [
                            'share_type' => 3,
                            'url' => 'https://nextcloud.test/s/12345',
                            'id' => '999',
                            'expiration' => now()->addDays(5)->toDateTimeString(),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->ensurePublicShare('/Photos/test.jpg');

        $this->assertEquals('https://nextcloud.test/s/12345', $result->url);
        $this->assertEquals('999', $result->shareId);
        $this->assertFalse($result->created);
    }

    public function test_ensure_public_share_creates_new_if_none_exists()
    {
        Http::fake([
            '*/ocs/v2.php/apps/files_sharing/api/v1/shares*path=*' => Http::response([
                'ocs' => [
                    'meta' => ['statuscode' => 404],
                ],
            ], 404),
            '*/ocs/v2.php/apps/files_sharing/api/v1/shares' => Http::response([
                'ocs' => [
                    'meta' => ['statuscode' => 100],
                    'data' => [
                        'url' => 'https://nextcloud.test/s/67890',
                        'id' => '888',
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->ensurePublicShare('/Photos/new.jpg');

        $this->assertEquals('https://nextcloud.test/s/67890', $result->url);
        $this->assertEquals('888', $result->shareId);
        $this->assertTrue($result->created);
    }

    public function test_ensure_public_share_throws_on_lookup_failure()
    {
        Http::fake([
            '*/ocs/v2.php/apps/files_sharing/api/v1/shares*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(NextcloudShareException::class);
        $this->expectExceptionMessage('Impossibile connettersi a Nextcloud per lookup share.');

        $this->service->ensurePublicShare('/Photos/test.jpg');
    }

    public function test_share_creation_post_is_not_automatically_retried()
    {
        $postAttempts = 0;

        Http::fake(function (Request $request) use (&$postAttempts) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'ocs' => ['meta' => ['statuscode' => 404]],
                ], 404);
            }

            $postAttempts++;

            return Http::response([
                'ocs' => ['meta' => ['statuscode' => 500]],
            ], 500);
        });

        try {
            $this->service->ensurePublicShare('/Photos/new.jpg');
            $this->fail('La creazione della share avrebbe dovuto fallire.');
        } catch (NextcloudShareException) {
            $this->assertSame(1, $postAttempts);
        }
    }

    public function test_revoke_public_share_by_id_returns_true_on_404_idempotent()
    {
        Http::fake([
            '*/ocs/v2.php/apps/files_sharing/api/v1/shares/999' => Http::response('', 404),
        ]);

        $this->assertTrue($this->service->revokePublicShareById('999'));
    }

    public function test_revoke_public_share_by_id_returns_true_on_success()
    {
        Http::fake([
            '*/ocs/v2.php/apps/files_sharing/api/v1/shares/999' => Http::response([
                'ocs' => [
                    'meta' => ['statuscode' => 200],
                ],
            ], 200),
        ]);

        $this->assertTrue($this->service->revokePublicShareById('999'));
    }

    public function test_acquire_locks_calculates_ttl_based_on_paths_count()
    {
        Cache::shouldReceive('lock')
            ->once()
            ->with('nextcloud_share_lock_'.md5('/Photos/test1.jpg'), 120) // max(120, 1 * 60)
            ->andReturn(
                $mockLock1 = \Mockery::mock(Lock::class)
            );
        $mockLock1->shouldReceive('block')->once()->with(10);

        $this->service->acquireLocksForPaths(['/Photos/test1.jpg']);

        Cache::shouldReceive('lock')
            ->once()
            ->with('nextcloud_share_lock_'.md5('/Photos/test1.jpg'), 180) // max(120, 3 * 60)
            ->andReturn(
                $mockLock2 = \Mockery::mock(Lock::class)
            );
        $mockLock2->shouldReceive('block')->once()->with(10);
        Cache::shouldReceive('lock')
            ->once()
            ->with('nextcloud_share_lock_'.md5('/Photos/test2.jpg'), 180)
            ->andReturn(
                $mockLock3 = \Mockery::mock(Lock::class)
            );
        $mockLock3->shouldReceive('block')->once()->with(10);
        Cache::shouldReceive('lock')
            ->once()
            ->with('nextcloud_share_lock_'.md5('/Photos/test3.jpg'), 180)
            ->andReturn(
                $mockLock4 = \Mockery::mock(Lock::class)
            );
        $mockLock4->shouldReceive('block')->once()->with(10);

        $this->service->acquireLocksForPaths(['/Photos/test1.jpg', '/Photos/test2.jpg', '/Photos/test3.jpg']);
    }

    public function test_revoke_public_share_by_id_returns_false_on_non_array_json()
    {
        Http::fake([
            '*/ocs/v2.php/apps/files_sharing/api/v1/shares/999' => Http::response('"string_not_array"', 200),
        ]);

        $this->assertFalse($this->service->revokePublicShareById('999'));
    }

    public function test_revoke_public_share_by_id_returns_false_on_missing_ocs()
    {
        Http::fake([
            '*/ocs/v2.php/apps/files_sharing/api/v1/shares/999' => Http::response(['data' => 'no_ocs_key'], 200),
        ]);

        $this->assertFalse($this->service->revokePublicShareById('999'));
    }

    public function test_release_locks_handles_exception()
    {
        $mockLock = \Mockery::mock(Lock::class);
        $mockLock->shouldReceive('release')->andThrow(new \Exception('Redis gone away'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to release lock', \Mockery::any());

        $this->service->releaseLocks([$mockLock]);

        $this->assertTrue(true);
    }
}
