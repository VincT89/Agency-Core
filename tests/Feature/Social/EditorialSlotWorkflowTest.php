<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\ScheduleSocialPostAction;
use App\Domain\Social\Actions\CancelEditorialSlotAction;
use App\Domain\Social\Actions\MarkEditorialSlotPublishedAction;
use App\Domain\Social\Actions\RequestSocialPostRegenerationAction;
use App\Enums\Social\EditorialSlotStatus;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialPostStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Exception;
use Mockery;

class EditorialSlotWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $photographer;
    protected Project $project;
    protected SocialPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->photographer = User::factory()->create(['role' => UserRole::Photographer]);
        
        $client = \App\Models\Client::factory()->create();
        
        $this->project = Project::factory()->create([
            'client_id' => $client->id,
        ]);
        $this->project->users()->attach($this->admin, [
            'role' => 'admin',
            'assignment_status' => 'active',
            'assigned_at' => now(),
        ]);
        
        $this->post = SocialPost::create([
            'project_id' => $this->project->id,
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'title' => 'Test Post',
            'status' => SocialPostStatus::ClientApproved,
        ]);
    }

    public function test_it_can_schedule_an_approved_post()
    {
        $action = app(ScheduleSocialPostAction::class);
        
        $slot = $action->execute(
            $this->post,
            now()->addDays(2)->toDateTimeString(),
            SocialPlatform::Instagram,
            'Test notes',
            $this->admin
        );

        $this->assertEquals(EditorialSlotStatus::Scheduled, $slot->status);
        $this->assertEquals(SocialPostStatus::Scheduled, $this->post->fresh()->status);
        $this->assertDatabaseHas('editorial_slots', [
            'social_post_id' => $this->post->id,
            'platform' => SocialPlatform::Instagram,
            'status' => 'scheduled'
        ]);
    }

    public function test_it_cannot_schedule_an_unapproved_post()
    {
        $this->post->update(['status' => SocialPostStatus::InternalReview]);

        $this->expectException(Exception::class);
        
        $action = app(ScheduleSocialPostAction::class);
        $action->execute(
            $this->post,
            now()->addDays(2)->toDateTimeString(),
            SocialPlatform::Instagram,
            null,
            $this->admin
        );
    }

    public function test_it_can_cancel_a_scheduled_slot()
    {
        $scheduleAction = app(ScheduleSocialPostAction::class);
        $slot = $scheduleAction->execute(
            $this->post,
            now()->addDays(2)->toDateTimeString(),
            SocialPlatform::Instagram,
            null,
            $this->admin
        );

        $cancelAction = app(CancelEditorialSlotAction::class);
        $cancelAction->execute($slot, $this->admin);

        $this->assertEquals(EditorialSlotStatus::Cancelled, $slot->fresh()->status);
        $this->assertEquals(SocialPostStatus::ClientApproved, $this->post->fresh()->status);
        $this->assertNotNull($slot->fresh()->cancelled_at);
    }

    public function test_it_can_mark_a_slot_as_published()
    {
        $scheduleAction = app(ScheduleSocialPostAction::class);
        $slot = $scheduleAction->execute(
            $this->post,
            now()->addDays(2)->toDateTimeString(),
            SocialPlatform::Instagram,
            null,
            $this->admin
        );

        $publishAction = app(MarkEditorialSlotPublishedAction::class);
        $publishAction->execute($slot, $this->admin);

        $this->assertEquals(EditorialSlotStatus::Published, $slot->fresh()->status);
        $this->assertEquals(SocialPostStatus::Published, $this->post->fresh()->status);
        $this->assertNotNull($slot->fresh()->published_at);
    }

    public function test_cannot_edit_scheduled_post()
    {
        $scheduleAction = app(ScheduleSocialPostAction::class);
        $scheduleAction->execute(
            $this->post,
            now()->addDays(2)->toDateTimeString(),
            SocialPlatform::Instagram,
            null,
            $this->admin
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Non puoi richiedere modifiche per un post pianificato o pubblicato. Annulla prima la pianificazione.");

        $action = app(RequestSocialPostRegenerationAction::class);
        $action->execute($this->post->fresh(), $this->admin, "test prompt");
    }

    public function test_photographer_cannot_schedule()
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $action = app(ScheduleSocialPostAction::class);
        $action->execute(
            $this->post,
            now()->addDays(2)->toDateTimeString(),
            SocialPlatform::Instagram,
            null,
            $this->photographer
        );
    }
}
