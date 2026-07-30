<?php

namespace Tests\Feature\Social;

use App\Domain\Social\DTOs\PreflightResult;
use App\Domain\Social\Services\MetaSnapshotPreflightRules;
use App\Domain\Social\Services\PublicationSnapshotPreflightService;
use App\Domain\Social\Services\TikTokSnapshotPreflightRules;
use App\Enums\Social\SocialPlatform;
use Mockery;
use Tests\TestCase;

class PublicationSnapshotPreflightTest extends TestCase
{
    public function test_invalid_platform_fails_preflight()
    {
        $metaRules = Mockery::mock(MetaSnapshotPreflightRules::class);
        $tiktokRules = Mockery::mock(TikTokSnapshotPreflightRules::class);

        $service = new PublicationSnapshotPreflightService($metaRules, $tiktokRules);

        $result = $service->runPreflight(['platform' => 'invalid_platform']);

        $this->assertFalse($result->isPass);
        $this->assertContains('Piattaforma non valida o non specificata.', $result->errors);
    }

    public function test_meta_preflight_is_delegated_correctly()
    {
        $metaRules = Mockery::mock(MetaSnapshotPreflightRules::class);
        $tiktokRules = Mockery::mock(TikTokSnapshotPreflightRules::class);

        $payload = ['platform' => SocialPlatform::Facebook->value, 'foo' => 'bar'];
        
        $metaRules->shouldReceive('validate')->with($payload)->once()->andReturn(new PreflightResult(true));

        $service = new PublicationSnapshotPreflightService($metaRules, $tiktokRules);
        $result = $service->runPreflight($payload);

        $this->assertTrue($result->isPass);
    }

    public function test_tiktok_preflight_is_delegated_correctly()
    {
        $metaRules = Mockery::mock(MetaSnapshotPreflightRules::class);
        $tiktokRules = Mockery::mock(TikTokSnapshotPreflightRules::class);

        $payload = ['platform' => SocialPlatform::Tiktok->value, 'foo' => 'bar'];
        
        $tiktokRules->shouldReceive('validate')->with($payload)->once()->andReturn(new PreflightResult(true));

        $service = new PublicationSnapshotPreflightService($metaRules, $tiktokRules);
        $result = $service->runPreflight($payload);

        $this->assertTrue($result->isPass);
    }
}
