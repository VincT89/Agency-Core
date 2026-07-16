<?php

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAuthenticationAndDeletionCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_authenticate_and_delete_own_profile()
    {
        // Setup
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        // Autenticazione
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));

        // Cancellazione profilo (current behavior)
        $response = $this->delete('/profile', [
            'password' => 'password',
        ]);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $response->assertRedirect('/');
    }

    public function test_admin_can_delete_another_user()
    {
        // Admin user can delete others
        $admin = User::factory()->create([
            'role' => UserRole::Admin
        ]);

        $targetUser = User::factory()->create();

        $response = $this->actingAs($admin)->delete("/users/{$targetUser->id}");

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }
}
