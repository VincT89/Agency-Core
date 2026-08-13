<?php

namespace Tests\Feature\Livewire;

use App\Enums\UserRole;
use App\Models\ClientReviewToken;
use App\Models\Expense;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\Shooting\Shoot;
use App\Models\User;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FrontendComponentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_marketing_campaign_calendar_renders_successfully(): void
    {
        $this->get(route('social.calendar'))
            ->assertOk()
            ->assertSeeHtml('id="marketing-calendar-client-filter"')
            ->assertSeeHtml('for="marketing-calendar-client-filter"')
            ->assertSeeHtml('id="marketing-calendar-campaign-filter"')
            ->assertSeeHtml('for="marketing-calendar-campaign-filter"')
            ->assertSeeHtml('id="marketing-calendar-platform-filter"')
            ->assertSeeHtml('for="marketing-calendar-platform-filter"')
            ->assertSee("prev: 'Periodo precedente'", false)
            ->assertSee("next: 'Periodo successivo'", false);
    }

    public function test_marketing_campaigns_index_renders_successfully(): void
    {
        $this->assertRouteRenders('marketing-campaigns.index');
    }

    public function test_marketing_campaign_create_renders_successfully(): void
    {
        $this->assertRouteRenders('marketing-campaigns.create');
    }

    public function test_marketing_campaign_show_renders_successfully(): void
    {
        $campaign = MarketingCampaign::factory()->create();

        $this->assertRouteRenders('marketing-campaigns.show', $campaign);
    }

    public function test_marketing_campaign_dialogs_render_as_accessible_teleported_dialogs(): void
    {
        $campaign = MarketingCampaign::factory()->create();
        $component = Livewire::test(MarketingCampaignShow::class, ['campaign' => $campaign]);

        foreach ([
            ['openCampaignModal', 'closeCampaignModal', 'showCampaignModal', 'Modifica Campagna'],
            ['openExtendModal', 'closeExtendModal', 'showExtendModal', 'Prolunga Campagna'],
            ['openRenewModal', 'closeRenewModal', 'showRenewModal', 'Rinnova Campagna'],
            ['openExtraModal', 'closeExtraModal', 'showExtraModal', 'Aggiungi Extra'],
            ['openInvoiceModal', 'closeInvoiceModal', 'showInvoiceModal', 'Genera Fattura'],
        ] as [$openMethod, $closeMethod, $property, $title]) {
            $component
                ->call($openMethod)
                ->assertSet($property, true)
                ->assertSee($title)
                ->assertSeeHtml('role="dialog"')
                ->assertSeeHtml('aria-modal="true"')
                ->call($closeMethod)
                ->assertSet($property, false);
        }
    }

    public function test_marketing_campaign_post_create_renders_successfully(): void
    {
        $campaign = MarketingCampaign::factory()->create();

        $this->assertRouteRenders(
            'marketing-campaigns.posts.create',
            $campaign
        );
    }

    public function test_marketing_campaign_post_show_renders_successfully(): void
    {
        [$campaign, $post] = $this->campaignAndPost();

        $this->assertRouteRenders(
            'marketing-campaigns.posts.show',
            ['campaign' => $campaign, 'post' => $post]
        );
    }

    public function test_public_marketing_campaign_post_review_renders_successfully(): void
    {
        [, $post] = $this->campaignAndPost();
        $token = ClientReviewToken::factory()->create([
            'reviewable_id' => $post->id,
            'reviewable_type' => MarketingCampaignPost::class,
            'marketing_campaign_post_version_id' => null,
        ]);

        $this->assertRouteRenders(
            'public.marketing-campaign-posts.review',
            ['token' => $token->token]
        );
    }

    public function test_social_shooting_requests_index_renders_successfully(): void
    {
        $this->assertRouteRenders('social.shooting.index');
    }

    public function test_social_shooting_create_request_renders_successfully(): void
    {
        $this->assertRouteRenders('social.shooting.create');
    }

    public function test_social_shooting_request_show_renders_successfully(): void
    {
        $this->assertRouteRenders(
            'social.shooting.show',
            Shoot::factory()->create()
        );
    }

    public function test_photography_shooting_my_shoots_index_renders_successfully(): void
    {
        $this->assertRouteRenders('photography.shooting.index');
    }

    public function test_photography_shooting_my_shoot_show_renders_successfully(): void
    {
        $this->assertRouteRenders(
            'photography.shooting.show',
            Shoot::factory()->create()
        );
    }

    public function test_admin_shooting_shoots_index_renders_successfully(): void
    {
        $this->assertRouteRenders('admin.shooting.index');
    }

    public function test_admin_shooting_shoot_show_renders_successfully(): void
    {
        $this->assertRouteRenders(
            'admin.shooting.show',
            Shoot::factory()->create()
        );
    }

    public function test_admin_social_agency_social_connections_renders_successfully(): void
    {
        $this->assertRouteRenders('admin.social.connections.index');
    }

    public function test_admin_social_social_operations_dashboard_renders_successfully(): void
    {
        $this->assertRouteRenders('admin.social.operations.index');
    }

    public function test_expenses_expenses_index_renders_successfully(): void
    {
        $this->assertRouteRenders('expenses.index');
    }

    public function test_expenses_expense_form_renders_successfully(): void
    {
        $this->assertRouteRenders('expenses.create');
    }

    public function test_expenses_expense_show_renders_successfully(): void
    {
        $expense = Expense::create([
            'user_id' => $this->admin->id,
            'title' => 'Test expense',
            'amount' => 10,
            'expense_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->assertRouteRenders('expenses.show', $expense);
    }

    public function test_dashboard_user_daily_notes_renders_successfully(): void
    {
        $this->assertRouteRenders('daily-notes.index');
    }

    private function assertRouteRenders(
        string $routeName,
        mixed $parameters = []
    ): void {
        $this->get(route($routeName, $parameters))->assertOk();
    }

    /**
     * @return array{MarketingCampaign, MarketingCampaignPost}
     */
    private function campaignAndPost(): array
    {
        $campaign = MarketingCampaign::factory()->create();
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
        ]);

        return [$campaign, $post];
    }
}
