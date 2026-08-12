<?php

namespace Tests\Feature\Social;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\PublicationStatus;
use App\Enums\UserRole;
use App\Livewire\Social\MarketingCampaignCalendar;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignShow;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MarketingCampaign $campaign;

    private ClientSocialAccount $socialAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $client = Client::factory()->create();
        $this->campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $this->socialAccount = ClientSocialAccount::factory()->create(['client_id' => $client->id]);
    }

    public function test_global_calendar_uses_real_publication_time_and_keeps_unscheduled_time_visible(): void
    {
        [$publishedPost, $scheduledPost, $cancelledPost] = $this->calendarPosts();

        Livewire::actingAs($this->admin)
            ->test(MarketingCampaignCalendar::class)
            ->call('fetchEvents')
            ->assertReturned(function (array $events) use ($publishedPost, $scheduledPost, $cancelledPost): bool {
                $events = collect($events)->keyBy('id');

                return $events->get('post_'.$publishedPost->id)['start'] === '2026-08-12T11:18:00'
                    && $events->get('post_'.$publishedPost->id)['allDay'] === false
                    && $events->get('post_'.$scheduledPost->id)['start'] === '2026-08-13T12:00:00'
                    && $events->get('post_'.$scheduledPost->id)['allDay'] === false
                    && ! $events->has('post_'.$cancelledPost->id);
            });
    }

    public function test_campaign_calendar_uses_real_publication_time_and_keeps_unscheduled_time_visible(): void
    {
        [$publishedPost, $scheduledPost, $cancelledPost] = $this->calendarPosts();

        Livewire::actingAs($this->admin)
            ->test(MarketingCampaignShow::class, ['campaign' => $this->campaign])
            ->call('fetchEvents')
            ->assertReturned(function (array $events) use ($publishedPost, $scheduledPost, $cancelledPost): bool {
                $events = collect($events)->keyBy('id');

                return $events->get($publishedPost->id)['start'] === '2026-08-12T11:18:00'
                    && $events->get($publishedPost->id)['allDay'] === false
                    && $events->get($scheduledPost->id)['start'] === '2026-08-13T12:00:00'
                    && $events->get($scheduledPost->id)['allDay'] === false
                    && ! $events->has($cancelledPost->id);
            });
    }

    private function calendarPosts(): array
    {
        $publishedPost = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $this->campaign->id,
            'title' => 'Pubblicato realmente',
            'status' => MarketingCampaignPostStatus::Published,
            'scheduled_date' => null,
            'scheduled_time' => null,
        ]);

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $publishedPost->id,
            'client_social_account_id' => $this->socialAccount->id,
            'status' => PublicationStatus::Published,
            'published_at' => '2026-08-12 11:18:00',
        ]);

        $scheduledPost = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $this->campaign->id,
            'title' => 'Pianificato senza ora',
            'status' => MarketingCampaignPostStatus::Approved,
            'scheduled_date' => '2026-08-13',
            'scheduled_time' => null,
        ]);

        $cancelledPost = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $this->campaign->id,
            'title' => 'Annullato',
            'status' => MarketingCampaignPostStatus::Cancelled,
            'scheduled_date' => '2026-08-14',
            'scheduled_time' => '10:00',
        ]);

        MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $this->campaign->id,
            'title' => 'Bozza senza data',
            'status' => MarketingCampaignPostStatus::Draft,
            'scheduled_date' => null,
            'scheduled_time' => null,
        ]);

        return [$publishedPost, $scheduledPost, $cancelledPost];
    }
}
