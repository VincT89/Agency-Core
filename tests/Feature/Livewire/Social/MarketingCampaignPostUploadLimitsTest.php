<?php

namespace Tests\Feature\Livewire\Social;

use App\Domain\Social\Services\MarketingCampaignPostMediaUploadPolicy;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use App\Enums\UserRole;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostCreate;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostUploadLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_upload_limits_are_visible_in_create_and_edit_forms(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        Livewire::actingAs($user)
            ->test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->assertSee('Massimo 10 media per post')
            ->assertSee('foto massimo 200 MB')
            ->assertSee('video massimo 500 MB')
            ->assertSee('nei limiti consentiti dal server')
            ->assertSee('caricali prima su Nextcloud');

        Livewire::actingAs($user)
            ->test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->assertSee('Massimo 10 media per post')
            ->assertSee('foto massimo 200 MB')
            ->assertSee('video massimo 500 MB')
            ->assertSee('nei limiti consentiti dal server')
            ->assertSee('caricali prima su Nextcloud');
    }

    public function test_media_upload_policy_enforces_separate_image_and_video_limits(): void
    {
        $imageAtLimit = UploadedFile::fake()->create(
            'foto.jpg',
            MarketingCampaignPostMediaUploadPolicy::IMAGE_MAX_KILOBYTES,
            'image/jpeg'
        );
        $imageOverLimit = UploadedFile::fake()->create(
            'foto.jpg',
            MarketingCampaignPostMediaUploadPolicy::IMAGE_MAX_KILOBYTES + 1,
            'image/jpeg'
        );
        $videoAtLimit = UploadedFile::fake()->create(
            'video.mp4',
            MarketingCampaignPostMediaUploadPolicy::VIDEO_MAX_KILOBYTES,
            'video/mp4'
        );
        $videoOverLimit = UploadedFile::fake()->create(
            'video.mp4',
            MarketingCampaignPostMediaUploadPolicy::VIDEO_MAX_KILOBYTES + 1,
            'video/mp4'
        );

        $this->assertFalse($this->mediaValidator($imageAtLimit)->fails());
        $this->assertTrue($this->mediaValidator($imageOverLimit)->fails());
        $this->assertFalse($this->mediaValidator($videoAtLimit)->fails());
        $this->assertTrue($this->mediaValidator($videoOverLimit)->fails());
    }

    public function test_livewire_temporary_upload_accepts_the_application_video_limit(): void
    {
        $this->assertSame('file|max:512000', config('livewire.temporary_file_upload.rules'));
        $this->assertSame(10, config('livewire.temporary_file_upload.max_upload_time'));
    }

    private function mediaValidator(UploadedFile $file): \Illuminate\Validation\Validator
    {
        return Validator::make(
            ['media' => $file],
            ['media' => MarketingCampaignPostMediaUploadPolicy::validationRules()]
        );
    }
}
