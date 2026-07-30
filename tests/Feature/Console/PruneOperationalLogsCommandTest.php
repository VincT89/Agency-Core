<?php

namespace Tests\Feature\Console;

use App\Models\IntegrationLog;
use App\Models\SystemCommandRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneOperationalLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_only_expired_finished_records(): void
    {
        config([
            'system-monitoring.retention.system_command_runs_days' => 10,
            'system-monitoring.retention.integration_logs_days' => 5,
        ]);

        $oldFinished = $this->systemRun('succeeded', now()->subDays(11));
        $oldRunning = $this->systemRun('running', now()->subDays(11));
        $recentFinished = $this->systemRun('failed', now()->subDays(2));
        $oldLog = $this->integrationLog(now()->subDays(6));
        $recentLog = $this->integrationLog(now()->subDay());

        $this->artisan('system:prune-operational-logs')->assertSuccessful();

        $this->assertModelMissing($oldFinished);
        $this->assertModelExists($oldRunning);
        $this->assertModelExists($recentFinished);
        $this->assertModelMissing($oldLog);
        $this->assertModelExists($recentLog);
    }

    public function test_dry_run_does_not_delete_records(): void
    {
        config([
            'system-monitoring.retention.system_command_runs_days' => 1,
            'system-monitoring.retention.integration_logs_days' => 1,
        ]);

        $run = $this->systemRun('succeeded', now()->subDays(2));
        $log = $this->integrationLog(now()->subDays(2));

        $this->artisan('system:prune-operational-logs', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertModelExists($run);
        $this->assertModelExists($log);
    }

    private function systemRun(string $status, $createdAt): SystemCommandRun
    {
        $run = SystemCommandRun::create([
            'run_uuid' => (string) Str::uuid(),
            'command' => 'test:command',
            'status' => $status,
            'started_at' => $createdAt,
        ]);
        $run->timestamps = false;
        $run->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $run;
    }

    private function integrationLog($createdAt): IntegrationLog
    {
        $log = IntegrationLog::create([
            'provider' => 'n8n',
            'direction' => 'inbound',
            'status' => 'processed',
        ]);
        $log->timestamps = false;
        $log->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $log;
    }
}
