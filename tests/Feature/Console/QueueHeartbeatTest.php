<?php

namespace Tests\Feature\Console;

use App\Jobs\QueueHeartbeatJob;
use App\Models\SystemHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_command_targets_every_configured_queue(): void
    {
        config(['system-monitoring.queues' => ['default', 'chatbot']]);
        Queue::fake();

        $this->artisan('system:dispatch-queue-heartbeats')
            ->assertSuccessful();

        Queue::assertPushedOn('default', QueueHeartbeatJob::class);
        Queue::assertPushedOn('chatbot', QueueHeartbeatJob::class);
        Queue::assertPushed(QueueHeartbeatJob::class, 2);
    }

    public function test_worker_probe_updates_its_queue_heartbeat(): void
    {
        $job = new QueueHeartbeatJob('social-publishing');
        $job->handle();

        $heartbeat = SystemHeartbeat::where(
            'name',
            'queue:social-publishing'
        )->firstOrFail();

        $this->assertTrue($heartbeat->last_seen_at->isToday());
        $this->assertSame(
            'social-publishing',
            $heartbeat->metadata['queue']
        );
    }
}
