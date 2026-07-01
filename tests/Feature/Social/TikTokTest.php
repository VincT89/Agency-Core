<?php

namespace Tests\Feature\Social;

use Tests\TestCase;

class TikTokTest extends TestCase
{
    // --- TikTok Token Management ---
    public function test_tiktok_refresh_token_action_successfully_extends_token(): void
    {
        $this->markTestIncomplete('TODO: implement tiktok token refresh test');
    }

    // --- TikTok Publishing & Capabilities ---
    public function test_tiktok_publishing_fails_when_capability_is_not_supported(): void
    {
        $this->markTestIncomplete('TODO: implement capability validation');
    }

    public function test_tiktok_publisher_successfully_posts_a_video_to_inbox_draft(): void
    {
        $this->markTestIncomplete('TODO: implement tiktok video publishing test');
    }

    public function test_tiktok_publisher_successfully_posts_photo_mode_images(): void
    {
        $this->markTestIncomplete('TODO: implement tiktok photo publishing test');
    }

    // --- TikTok Status & Validation ---
    public function test_tiktok_status_polling_retrieves_accurate_status(): void
    {
        $this->markTestIncomplete('TODO: implement status polling test');
    }

    public function test_tiktok_photo_validation_service_enforces_constraints(): void
    {
        $this->markTestIncomplete('TODO: implement tiktok photo validation test');
    }

    public function test_tiktok_video_validation_service_enforces_constraints(): void
    {
        $this->markTestIncomplete('TODO: implement tiktok video validation test');
    }
}
