<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_authenticated_routes_render(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'password_changed_at' => now(),
        ]);

        $this->actingAs($user);

        $routes = [
            route('dashboard'),
            route('clients.index'),
            route('projects.index'),
            route('tickets.index'),
            route('tasks.index'),
            route('teams.index'),
            route('social.calendar'),
            route('marketing-campaigns.index'),
            route('social.shooting.index'),
            route('photography.shooting.index'),
            route('admin.shooting.index'),
            route('admin.social.connections.index'),
            route('admin.social.operations.index'),
            route('expenses.index'),
            route('calendar-events.index'),
            route('invoices.index'),
            route('payments.index'),
            route('economic-summary.index'),
            route('hosting-services.index'),
            route('users.index'),
            route('audit-logs.index'),
            route('daily-notes.index'),
        ];

        foreach ($routes as $url) {
            $response = $this->get($url);
            if ($response->status() !== 200) {
                dump("Failed URL: $url with status " . $response->status());
            }
            $response->assertOk();
        }
    }
}
