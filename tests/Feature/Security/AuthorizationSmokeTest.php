<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthorizationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_sensitive_routes(): void
    {
        $routes = [
            route('dashboard'),
            route('clients.index'),
            route('invoices.index'),
            route('admin.social.connections.index'),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_admin_can_access_sensitive_routes(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'password_changed_at' => now(),
        ]);

        $this->actingAs($user);

        $sensitiveRoutes = [
            route('clients.index'),
            route('admin.social.connections.index'),
            route('expenses.index'),
        ];

        foreach ($sensitiveRoutes as $url) {
            $this->get($url)->assertOk();
        }
    }
}
