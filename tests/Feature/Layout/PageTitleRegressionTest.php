<?php

namespace Tests\Feature\Layout;

use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTitleRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($this->admin);
    }

    public function test_livewire_areas_do_not_fall_back_to_dashboard_title(): void
    {
        $campaign = MarketingCampaign::factory()->create(['name' => 'Campagna Agosto']);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'title' => 'Post di prova',
        ]);
        $expense = Expense::create([
            'user_id' => $this->admin->id,
            'title' => 'Licenza software',
            'amount' => 10,
            'expense_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->assertPageTitle('daily-notes.index', [], 'Blocco Note');
        $this->assertPageTitle('expenses.index', [], 'Spese');
        $this->assertPageTitle('expenses.create', [], 'Nuova spesa');
        $this->assertPageTitle('expenses.show', $expense, 'Spesa: Licenza software');
        $this->assertPageTitle('marketing-campaigns.index', [], 'Progetti Marketing');
        $this->assertPageTitle('marketing-campaigns.create', [], 'Nuovo progetto marketing');
        $this->assertPageTitle('marketing-campaigns.show', $campaign, 'Campagna Agosto');
        $this->assertPageTitle('marketing-campaigns.posts.create', $campaign, 'Nuovo post · Campagna Agosto');
        $this->assertPageTitle(
            'marketing-campaigns.posts.show',
            ['campaign' => $campaign, 'post' => $post],
            'Post di prova'
        );
    }

    private function assertPageTitle(string $routeName, mixed $parameters, string $title): void
    {
        $this->get(route($routeName, $parameters))
            ->assertOk()
            ->assertSee("<title>{$title}</title>", false);
    }
}
