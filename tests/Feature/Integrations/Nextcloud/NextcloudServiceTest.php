<?php

namespace Tests\Feature\Integrations\Nextcloud;

use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->normalizePath('/Photos/../secret.jpg');
    }

    public function test_normalize_path_aborts_on_suspicious_encoding()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        // Doppio encode di spazio o altro carattere
        $this->service->normalizePath('/Photos/test%2520file.jpg');
    }

    public function test_list_files_handles_malformed_xml_gracefully()
    {
        Http::fake([
            '*' => Http::response('NOT XML AT ALL', 200),
        ]);

        $result = $this->service->listFiles('/Photos');
        
        $this->assertNull($result);
    }
}
