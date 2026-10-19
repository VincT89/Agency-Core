<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveAccountSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspending_admin_or_enabled_developer_ends_existing_login_on_next_request(): void
    {
        foreach ([UserRole::Admin, UserRole::Developer] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            DB::table('users')->where('id', $user->id)->update(['admin_access' => true]);
            $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])
                ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
            $rememberToken = $user->refresh()->remember_token;
            $this->get('/users')->assertOk();
            DB::table('users')->where('id', $user->id)->update(['status' => 'inactive']);
            Auth::forgetGuards();

            $this->get('/users')->assertRedirect('/login')->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertNotSame($rememberToken, $user->refresh()->remember_token);
            $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => $role->value, 'status' => 'inactive']);
        }
    }

    public function test_suspended_session_cannot_update_users_or_send_livewire_requests(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'status' => 'inactive']);
        $target = User::factory()->create();
        $this->actingAs($user)->patchJson(route('users.update', $target), [
            'name' => 'Nome non autorizzato', 'email' => $target->email, 'role' => 'admin', 'status' => 'active',
        ])->assertUnauthorized()->assertJsonPath('message', 'Il tuo account è disattivato o sospeso.');
        $this->assertGuest();
        $this->assertSame(UserRole::Developer, $target->refresh()->role);
        $this->assertNotSame('Nome non autorizzato', $target->name);
        $this->actingAs($user)->postJson(app('livewire')->getUpdateUri(), ['components' => []])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_suspended_operational_user_is_blocked_from_their_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::Developer, 'status' => 'inactive']);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reactivated_user_must_log_in_again(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'status' => 'inactive']);
        $this->actingAs($user)->get('/users')->assertRedirect('/login');
        DB::table('users')->where('id', $user->id)->update(['status' => 'active']);
        $this->get('/users')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/users');
        $this->get('/users')->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
