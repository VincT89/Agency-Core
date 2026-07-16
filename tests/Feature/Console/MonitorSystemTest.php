<?php

namespace Tests\Feature\Console;

use App\Models\SystemHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonitorSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['system-monitoring.scheduler_timeout_minutes' => 5]);
        config(['system-monitoring.stuck_jobs_timeout_minutes' => 30]);
        config(['system-monitoring.failed_jobs_threshold_per_hour' => 10]);
    }

    public function test_it_returns_0_when_everything_is_ok(): void
    {
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now(),
            'metadata' => ['status' => 'ok']
        ]);

        $this->artisan('monitor:system')
            ->expectsOutputToContain('Stato del sistema: OK')
            ->assertExitCode(0);
    }

    public function test_it_returns_2_when_scheduler_heartbeat_is_missing(): void
    {
        $this->artisan('monitor:system')
            ->expectsOutputToContain('[CRITICAL] Scheduler heartbeat mancante.')
            ->assertExitCode(2);
    }

    public function test_it_returns_2_when_scheduler_is_stuck(): void
    {
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now()->subMinutes(10),
            'metadata' => ['status' => 'ok']
        ]);

        $this->artisan('monitor:system')
            ->expectsOutputToContain('[CRITICAL] Scheduler fermo da oltre 5 minuti.')
            ->assertExitCode(2);
    }

    public function test_it_returns_1_when_jobs_are_stuck(): void
    {
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now(),
            'metadata' => ['status' => 'ok']
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(40)->timestamp,
            'created_at' => now()->subMinutes(40)->timestamp,
        ]);

        $this->artisan('monitor:system')
            ->expectsOutputToContain('[WARNING] Ci sono 1 job fermi in coda da oltre 30 minuti.')
            ->assertExitCode(1);
    }

    public function test_it_returns_1_when_too_many_failed_jobs(): void
    {
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now(),
            'metadata' => ['status' => 'ok']
        ]);

        for ($i = 0; $i < 15; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Exception',
                'failed_at' => now()->subMinutes(10),
            ]);
        }

        $this->artisan('monitor:system')
            ->expectsOutputToContain('[WARNING] Registrati 15 job falliti nell\'ultima ora.')
            ->assertExitCode(1);
    }
}
