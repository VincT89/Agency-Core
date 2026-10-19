<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeveloperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function developer(bool $enabled = true, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => UserRole::Developer, 'status' => 'active'], $attributes));
        DB::table('users')->where('id', $user->id)->update(['admin_access' => $enabled]);

        return $user->refresh();
    }

    private function userData(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name, 'email' => $user->email,
            'role' => $user->role->value, 'status' => $user->status,
        ], $overrides);
    }

    public function test_database_grant_preserves_developer_identity_and_revoke_removes_access(): void
    {
        $user = User::factory()->create(['role' => UserRole::Developer])->refresh();
        $original = $user->getRawOriginal();
        $this->assertFalse($user->admin_access);
        $this->assertFalse(Gate::forUser($user)->allows('system.admin'));

        DB::table('users')->where('id', $user->id)->update(['admin_access' => true]);
        $user->refresh();
        $this->assertSame(UserRole::Developer, $user->role);
        $this->assertSame('Developer', $user->role->label());
        $this->assertTrue($user->isDeveloper());
        $this->assertTrue($user->canAccessFinance());
        $this->assertTrue($user->canManageMarketing());
        $this->assertTrue($user->canViewManagementDashboard());
        $this->assertTrue($user->canViewAuditLogs());
        $this->assertTrue(Gate::forUser($user)->allows('system.admin'));
        unset($original['admin_access']);
        $this->assertSame($original, array_diff_key($user->getRawOriginal(), ['admin_access' => true]));

        DB::table('users')->where('id', $user->id)->update(['admin_access' => false]);
        $user->refresh();
        $this->assertFalse(Gate::forUser($user)->allows('system.admin'));
        $this->assertFalse($user->canAccessFinance());
        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_override_does_not_elevate_other_operational_roles(): void
    {
        foreach (UserRole::cases() as $role) {
            if (in_array($role, [UserRole::Admin, UserRole::Developer], true)) {
                continue;
            }
            $user = $this->developer(true, ['role' => $role]);
            $this->assertFalse(Gate::forUser($user)->allows('system.admin'), $role->value);
            $this->assertFalse(User::administrators()->whereKey($user->id)->exists(), $role->value);
        }
    }

    public function test_enabled_developer_has_admin_dashboard_and_protected_modules(): void
    {
        $user = $this->developer();
        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertSame(3, substr_count($response->getContent(), 'table-responsive dashboard-operations-table-wrap'));
        foreach (['Fatture', 'Pagamenti', 'Utenti', 'Team', 'Disponibilità team'] as $label) {
            $response->assertSeeText($label);
        }
        foreach (['/users', '/invoices', '/payments', '/expenses', '/billing-profile', '/economic-summary', '/audit-logs', '/admin/social/connections'] as $path) {
            $this->get($path)->assertOk();
        }

        $target = $this->developer(false);
        $this->patch(route('users.update', $target), $this->userData($target, ['name' => 'Utente aggiornato']))
            ->assertSessionHasNoErrors()->assertRedirect(route('users.index'));
        $this->assertSame('Utente aggiornato', $target->refresh()->name);
        $this->assertFalse($target->admin_access);
    }

    public function test_unflagged_developer_keeps_existing_restrictions(): void
    {
        $this->actingAs($this->developer(false))->get('/dashboard')->assertOk()
            ->assertDontSeeText('Fatture')->assertDontSeeText('Utenti');
        foreach (['/users', '/invoices', '/payments', '/expenses', '/billing-profile', '/economic-summary', '/audit-logs', '/admin/social/connections'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $target = $this->developer(false);
        $this->patch(route('users.update', $target), $this->userData($target, ['role' => 'admin']))->assertForbidden();
        $this->assertSame(UserRole::Developer, $target->refresh()->role);
    }

    public function test_grant_enables_global_project_visibility_without_changing_memberships(): void
    {
        $project = Project::factory()->create();
        $user = $this->developer(false);
        $this->actingAs($user)->get(route('projects.show', $project))->assertNotFound();
        DB::table('users')->where('id', $user->id)->update(['admin_access' => true]);
        $this->actingAs($user->refresh())->get(route('projects.show', $project))->assertOk();
        $this->assertSame(0, DB::table('project_user')->where('user_id', $user->id)->count());
        DB::table('users')->where('id', $user->id)->update(['admin_access' => false]);
        $this->actingAs($user->refresh())->get(route('projects.show', $project))->assertNotFound();
    }

    public function test_privilege_is_absent_from_forms_serialization_and_mass_assignment(): void
    {
        $user = $this->developer();
        $this->assertFalse($user->isFillable('admin_access'));
        $this->assertArrayNotHasKey('admin_access', $user->toArray());
        $user->fill(['admin_access' => false])->save();
        $this->assertTrue($user->refresh()->admin_access);
        $this->actingAs($user);
        foreach ([route('users.create'), route('users.edit', $user), route('profile.edit')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('admin_access');
        }
        $this->get(route('users.edit', $user))->assertSee('value="developer" selected', false);
    }

    public function test_profile_and_admin_forms_reject_forged_privilege_fields(): void
    {
        $user = $this->developer(false);
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'admin_access' => true,
        ])->assertSessionHasErrors('admin_access');
        $this->assertFalse($user->refresh()->admin_access);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->patch(route('users.update', $user), $this->userData($user, ['admin_access' => true]))
            ->assertSessionHasErrors('admin_access');
        $this->assertFalse($user->refresh()->admin_access);
        $this->post(route('users.store'), [
            'name' => 'Tentativo', 'email' => 'forged@example.test', 'role' => 'developer',
            'password' => 'test-password', 'password_confirmation' => 'test-password', 'admin_access' => true,
        ])->assertSessionHasErrors('admin_access');
        $this->assertDatabaseMissing('users', ['email' => 'forged@example.test']);

        $enabled = $this->developer();
        $this->patch(route('users.update', $enabled), $this->userData($enabled, ['admin_access' => false]))
            ->assertSessionHasErrors('admin_access');
        $this->assertTrue($enabled->refresh()->admin_access);
    }

    public function test_last_enabled_developer_can_save_details_but_not_lose_admin_access(): void
    {
        $user = $this->developer();
        $this->assertTrue($user->isLastActiveAdministrator());
        $this->actingAs($user)->patch(route('users.update', $user), $this->userData($user, ['name' => 'Developer aggiornato']))
            ->assertSessionHasNoErrors()->assertRedirect(route('users.index'));
        $this->assertSame('Developer aggiornato', $user->refresh()->name);
        foreach ([['role' => 'marketing'], ['status' => 'inactive']] as $changes) {
            $this->patch(route('users.update', $user), $this->userData($user, $changes))->assertSessionHasErrors('role');
            $this->assertSame(UserRole::Developer, $user->refresh()->role);
            $this->assertSame('active', $user->status);
        }
        $this->from(route('profile.edit'))->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'admin_access' => true]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_original_admin_can_be_disabled_after_an_active_developer_is_enabled(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $user = $this->developer();
        $this->assertFalse($admin->isLastActiveAdministrator());
        $this->actingAs($user)->from(route('users.index'))->post(route('users.toggle-status', $admin))
            ->assertRedirect(route('users.index'))->assertSessionHas('success');
        $this->assertSame('inactive', $admin->refresh()->status);
        $this->assertTrue($user->isLastActiveAdministrator());
    }

    public function test_inactive_enabled_developer_does_not_count_as_an_available_admin_and_cannot_log_in(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $user = $this->developer(true, ['status' => 'inactive']);
        $this->assertTrue($admin->isLastActiveAdministrator());
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_enabled_developer_can_delete_own_profile_if_another_admin_remains(): void
    {
        User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $user = $this->developer();
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_enabled_developer_can_filter_calendar_by_department(): void
    {
        $user = $this->developer();
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        foreach ([$user, $marketing] as $assignee) {
            CalendarEvent::create([
                'created_by' => $marketing->id, 'assigned_to' => $assignee->id,
                'title' => $assignee->role->value, 'type' => 'internal_meeting', 'status' => 'scheduled',
                'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour(),
            ]);
        }
        $this->actingAs($user)->getJson(route('calendar-events.index', ['department' => 'marketing']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'marketing');
    }

    public function test_migration_does_not_elevate_or_change_existing_accounts(): void
    {
        $users = User::factory()->count(2)->create();
        $before = $users->map(fn (User $user) => $user->refresh()->getRawOriginal())->all();
        $migration = require database_path('migrations/2026_10_19_140000_add_admin_access_to_users.php');
        $migration->down();
        $migration->up();
        $this->assertSame($before, $users->map(fn (User $user) => $user->refresh()->getRawOriginal())->all());
        $this->assertSame(0, User::administrators()->count());
    }

    public function test_rollback_cannot_remove_the_only_available_admin_access(): void
    {
        $this->developer();
        $migration = require database_path('migrations/2026_10_19_140000_add_admin_access_to_users.php');
        try {
            $migration->down();
            $this->fail('Rollback must preserve the last active administrator.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('amministratore attivo', $exception->getMessage());
            $this->assertTrue(Schema::hasColumn('users', 'admin_access'));
        }
    }
}
