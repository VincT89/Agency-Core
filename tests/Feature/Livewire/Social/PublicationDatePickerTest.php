<?php

namespace Tests\Feature\Livewire\Social;

use App\Enums\UserRole;
use App\Livewire\Social\PublicationDatePicker;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PublicationDatePickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => UserRole::Marketing]));
        $this->travelTo(now()->setDate(2026, 9, 18));
    }

    public function test_calendar_counts_client_posts_across_campaigns_and_excludes_cancelled_archived_and_current_post(): void
    {
        $campaign = MarketingCampaign::factory()->create();
        $otherCampaign = MarketingCampaign::factory()->create(['client_id' => $campaign->client_id]);
        $attrs = ['marketing_campaign_id' => $campaign->id, 'scheduled_date' => '2026-09-18', 'published_at' => null, 'status' => 'draft'];
        $current = MarketingCampaignPost::factory()->create($attrs);
        MarketingCampaignPost::factory()->create($attrs);
        MarketingCampaignPost::factory()->create(array_merge($attrs, ['marketing_campaign_id' => $otherCampaign->id]));
        MarketingCampaignPost::factory()->create(array_merge($attrs, ['status' => 'cancelled']));
        MarketingCampaignPost::factory()->create(array_merge($attrs, ['archived_at' => now()]));
        MarketingCampaignPost::factory()->create(array_merge($attrs, ['marketing_campaign_id' => MarketingCampaign::factory()]));

        Livewire::test(PublicationDatePicker::class, ['campaign' => $campaign, 'postId' => $current->id])
            ->call('toggle')
            ->assertSeeHtml('data-date="2026-09-18" data-publications="2"')
            ->assertSee('2 pubblicazioni nella settimana')
            ->call('selectDate', '2026-09-21')->assertSet('value', '2026-09-21')->assertSet('open', false)
            ->call('selectDate', null)->assertSet('value', null);
    }

    public function test_actual_publication_date_takes_precedence_and_month_navigation_does_not_skip_february(): void
    {
        $campaign = MarketingCampaign::factory()->create();
        MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id, 'status' => 'published',
            'scheduled_date' => '2026-09-17', 'published_at' => '2026-09-18 10:00:00',
        ]);
        Livewire::test(PublicationDatePicker::class, ['campaign' => $campaign])->call('toggle')
            ->assertSeeHtml('data-date="2026-09-18" data-publications="1"')
            ->assertSeeHtml('data-date="2026-09-17" data-publications="0"')
            ->set('month', '2027-01-31')->call('moveMonth', 1)->assertSet('month', '2027-02-01');
    }

    public function test_invalid_dates_and_unauthorized_access_are_rejected(): void
    {
        $campaign = MarketingCampaign::factory()->create();
        Livewire::test(PublicationDatePicker::class, ['campaign' => $campaign])
            ->call('selectDate', '2026-02-31')->assertHasErrors('date');
        $this->actingAs(User::factory()->create(['role' => UserRole::Commercial]));
        Livewire::test(PublicationDatePicker::class, ['campaign' => $campaign])->assertForbidden();
    }
}
