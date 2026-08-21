<?php

namespace Tests\Feature\Layout;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardResponsiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_tables_use_the_responsive_table_container(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame(
            3,
            substr_count($response->getContent(), 'table-responsive dashboard-operations-table-wrap')
        );
    }
}
