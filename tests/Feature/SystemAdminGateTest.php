<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SystemAdminGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_gate_allows_only_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $administration = User::factory()->create(['role' => UserRole::Administration]);
        $developer = User::factory()->create(['role' => UserRole::Developer]);
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);

        $this->assertTrue(Gate::forUser($admin)->allows('system.admin'));
        $this->assertFalse(Gate::forUser($administration)->allows('system.admin'));
        $this->assertFalse(Gate::forUser($developer)->allows('system.admin'));
        $this->assertFalse(Gate::forUser($marketing)->allows('system.admin'));
    }
}
