<?php

namespace Tests\Unit\Social;

use App\Domain\Social\Services\TikTokSnapshotPreflightRules;
use Tests\TestCase;

class TikTokSnapshotPreflightRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tiktok.delivery_mode' => 'direct']);
    }

    public function test_direct_snapshot_requires_explicit_options_and_consent(): void
    {
        $payload = $this->validPayload();
        $payload['target']['privacy_options'] = [];
        $payload['platform_options']['creator_consent_confirmed'] = false;

        $result = app(TikTokSnapshotPreflightRules::class)->validate($payload);

        $this->assertFalse($result->isPass);
        $this->assertContains(
            'TikTok Direct Post requires an explicit valid privacy level.',
            $result->errors
        );
        $this->assertContains(
            'TikTok Direct Post requires explicit creator consent.',
            $result->errors
        );
    }

    public function test_valid_direct_snapshot_passes(): void
    {
        $result = app(TikTokSnapshotPreflightRules::class)
            ->validate($this->validPayload());

        $this->assertTrue($result->isPass, implode(' ', $result->errors));
    }

    public function test_branded_content_rejects_private_visibility(): void
    {
        $payload = $this->validPayload();
        $payload['platform_options']['commercial_content_disclosed'] = true;
        $payload['platform_options']['brand_content_toggle'] = true;

        $result = app(TikTokSnapshotPreflightRules::class)->validate($payload);

        $this->assertFalse($result->isPass);
        $this->assertContains(
            'TikTok branded content cannot use the selected privacy level.',
            $result->errors
        );
    }

    private function validPayload(): array
    {
        return [
            'media' => [[
                'media_id' => 1,
                'media_type' => 'video',
                'mime_type' => 'video/mp4',
            ]],
            'target' => [
                'privacy_options' => [
                    'privacy_level' => 'SELF_ONLY',
                    'disable_comment' => true,
                    'disable_duet' => true,
                    'disable_stitch' => true,
                ],
            ],
            'platform_options' => [
                'delivery_mode' => 'direct',
                'commercial_content_disclosed' => false,
                'brand_content_toggle' => false,
                'brand_organic_toggle' => false,
                'creator_consent_confirmed' => true,
            ],
        ];
    }
}
