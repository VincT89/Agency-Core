<?php

namespace Tests\Feature\Social;

use App\Enums\Social\MarketingCampaignPostCommentType;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Livewire\Public\MarketingCampaignPostReview;
use App\Models\Client;
use App\Models\ClientReviewToken;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_is_blocked_if_media_delivery_fails()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'nextcloud',
            'nextcloud_share_url' => null, // This will throw MarketingCampaignPostMediaDeliveryException
        ]);

        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);

        $token = ClientReviewToken::create([
            'token' => 'VALID_TOKEN_123',
            'reviewable_type' => MarketingCampaignPost::class,
            'reviewable_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
            'expires_at' => now()->addDays(7),
        ]);

        $component = Livewire::test(MarketingCampaignPostReview::class, ['token' => 'VALID_TOKEN_123']);

        $component->assertStatus(409);
    }

    public function test_client_can_approve_current_version_and_identity_cannot_be_tampered(): void
    {
        [$client, $post, $token] = $this->reviewScenario();

        Livewire::test(MarketingCampaignPostReview::class, ['token' => $token->token])
            ->set('clientName', 'Nome manomesso')
            ->set('clientEmail', 'attacker@example.test')
            ->call('approve')
            ->assertHasNoErrors()
            ->assertSet('successMessage', 'Post approvato con successo. Grazie!');

        $this->assertSame(MarketingCampaignPostStatus::ClientApproved, $post->fresh()->status);
        $this->assertNotNull($token->fresh()->used_at);
        $this->assertDatabaseHas('marketing_campaign_post_comments', [
            'marketing_campaign_post_id' => $post->id,
            'client_name' => $client->name,
            'client_email' => $client->email,
            'type' => MarketingCampaignPostCommentType::Approval->value,
        ]);
        $this->assertDatabaseMissing('marketing_campaign_post_comments', [
            'marketing_campaign_post_id' => $post->id,
            'client_email' => 'attacker@example.test',
        ]);
    }

    public function test_client_can_request_changes_without_redirecting_to_consumed_token(): void
    {
        [$client, $post, $token] = $this->reviewScenario();

        Livewire::test(MarketingCampaignPostReview::class, ['token' => $token->token])
            ->set('clientName', 'Nome manomesso')
            ->set('clientEmail', 'attacker@example.test')
            ->set('commentBody', 'Correggere il colore dello sfondo.')
            ->call('requestChanges')
            ->assertHasNoErrors()
            ->assertSet(
                'successMessage',
                'Richiesta di modifiche inviata con successo. Il team si metterà al lavoro a breve.'
            );

        $this->assertSame(MarketingCampaignPostStatus::ClientChangesRequested, $post->fresh()->status);
        $this->assertNotNull($token->fresh()->used_at);
        $this->assertDatabaseHas('marketing_campaign_post_comments', [
            'marketing_campaign_post_id' => $post->id,
            'client_name' => $client->name,
            'client_email' => $client->email,
            'body' => 'Correggere il colore dello sfondo.',
            'type' => MarketingCampaignPostCommentType::ChangeRequest->value,
        ]);
    }

    private function reviewScenario(): array
    {
        Storage::fake('public');

        $client = Client::factory()->create([
            'name' => 'Cliente Autorizzato',
            'email' => 'cliente@example.test',
        ]);
        $campaign = MarketingCampaign::factory()->create([
            'client_id' => $client->id,
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::SentToClient->value,
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'disk' => 'public',
            'path' => 'marketing/campaign-posts/review.jpg',
            'source' => 'local',
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
        ]);
        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);

        $token = ClientReviewToken::create([
            'token' => 'REVIEW_TOKEN_'.$post->id,
            'reviewable_type' => MarketingCampaignPost::class,
            'reviewable_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
            'expires_at' => now()->addDays(7),
        ]);

        return [$client, $post, $token];
    }
}
