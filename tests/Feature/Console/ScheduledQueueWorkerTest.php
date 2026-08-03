<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduledQueueWorkerTest extends TestCase
{
    public function test_production_scheduler_processes_every_application_queue(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => $event->description === 'process-agency-core-queues');

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertStringContainsString(
            'queue:work --queue=social-publishing,social-reconciliation,chatbot,default',
            $event->command,
        );
        $this->assertStringContainsString('--stop-when-empty', $event->command);
        $this->assertStringContainsString('--tries=3', $event->command);
        $this->assertStringContainsString('--timeout=600', $event->command);
        $this->assertTrue($event->withoutOverlapping);
    }
}
