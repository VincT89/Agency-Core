<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Requests\StoreAttachmentRequest;
use App\Livewire\Shared\AttachmentManager;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TaskAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Storage::fake('attachments');
        $this->author = User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]);
        $this->actingAs($this->author);
        $this->task = Task::factory()->create(['project_id' => null, 'created_by' => $this->author->id, 'assigned_to' => $this->author->id]);
    }

    public function test_excel_files_up_to_fifteen_megabytes_are_saved_individually(): void
    {
        $component = Livewire::test(AttachmentManager::class, ['model' => $this->task]);
        foreach ([6759, 12 * 1024, 15 * 1024] as $index => $kilobytes) {
            $name = 'DEMO-'.$kilobytes.'.xls';
            $component->set('file', UploadedFile::fake()->create($name, $kilobytes, 'application/vnd.ms-excel'));
            $this->assertSame($index, $this->task->attachments()->count());
            $component->call('saveTaskAttachment')->assertReturned(['saved' => true])->assertSet('file', null);
            $attachment = $this->task->attachments()->where('original_name', $name)->sole();
            $this->assertSame($kilobytes * 1024, (int) $attachment->size);
            $this->assertSame($this->author->id, $attachment->uploaded_by);
            $this->assertSame('application/vnd.ms-excel', $attachment->mime_type);
            Storage::disk('attachments')->assertExists($attachment->path);
            $this->get(route('attachments.download', $attachment))->assertOk();
        }
        $this->assertSame(3, $this->task->attachments()->count());
    }

    public function test_an_oversized_file_does_not_prevent_saving_the_next_file(): void
    {
        $component = Livewire::test(AttachmentManager::class, ['model' => $this->task]);
        $component->set('file', UploadedFile::fake()->create('DEMO-large.xls', 15 * 1024 + 1, 'application/vnd.ms-excel'))
            ->call('saveTaskAttachment')
            ->assertReturned(['saved' => false, 'message' => 'Il file supera il limite di 15 MB.'])
            ->assertSet('file', null);
        $this->assertSame(0, $this->task->attachments()->count());
        $component->set('file', UploadedFile::fake()->createWithContent('DEMO-valid.txt', 'Contenuto dimostrativo'))
            ->call('saveTaskAttachment')->assertReturned(['saved' => true]);
        $this->assertSame(1, $this->task->attachments()->count());
    }

    public function test_invalid_content_cannot_bypass_validation_with_an_excel_extension(): void
    {
        Livewire::test(AttachmentManager::class, ['model' => $this->task])
            ->set('file', UploadedFile::fake()->create('DEMO-invalid.xls', 100, 'application/x-php'))
            ->call('saveTaskAttachment')
            ->assertReturned(fn ($result) => $result['saved'] === false && str_contains($result['message'], 'Formato non riconosciuto'));
        $this->assertSame(0, $this->task->attachments()->count());
    }

    public function test_unauthorized_user_cannot_save_an_attachment(): void
    {
        $outsider = User::factory()->create(['role' => UserRole::Developer]);
        $this->actingAs($outsider);
        Livewire::test(AttachmentManager::class, ['model' => $this->task])->call('saveTaskAttachment')->assertForbidden();
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_a_failed_database_write_removes_the_new_file_and_reports_failure(): void
    {
        $component = Livewire::test(AttachmentManager::class, ['model' => $this->task])
            ->set('file', UploadedFile::fake()->createWithContent('DEMO-failure.txt', 'Contenuto dimostrativo'));
        DB::statement("CREATE TRIGGER fail_test_attachment BEFORE INSERT ON attachments BEGIN SELECT RAISE(FAIL, 'Simulated insert failure'); END");
        try {
            $component->call('saveTaskAttachment')
                ->assertReturned(['saved' => false, 'message' => 'Non è stato possibile salvare il file. Riprova.']);
            $this->assertDatabaseCount('attachments', 0);
            $this->assertSame([], Storage::disk('attachments')->allFiles('task/'.$this->task->id));
        } finally {
            DB::statement('DROP TRIGGER fail_test_attachment');
        }
    }

    public function test_legacy_uploads_keep_their_limit_and_single_file_behavior(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);
        $client = Client::factory()->create();
        Livewire::test(AttachmentManager::class, ['model' => $client])
            ->set('file', UploadedFile::fake()->create('DEMO-large.xls', 12 * 1024, 'application/vnd.ms-excel'))
            ->assertHasErrors(['file' => 'max'])
            ->set('file', UploadedFile::fake()->createWithContent('DEMO-legacy.txt', 'Contenuto dimostrativo'))
            ->assertHasNoErrors();
        $this->assertSame(1, $client->attachments()->count());
        $this->assertSame(15 * 1024, StoreAttachmentRequest::maxKilobytesFor('task'));
        $this->assertSame(10 * 1024, StoreAttachmentRequest::maxKilobytesFor('client'));
    }

    public function test_task_http_uploads_use_the_same_limit(): void
    {
        $data = ['attachable_type' => 'task', 'attachable_id' => $this->task->id, 'type' => 'document'];
        $this->post(route('attachments.store'), $data + ['file' => UploadedFile::fake()->create('DEMO-valid.xls', 12 * 1024, 'application/vnd.ms-excel')])
            ->assertSessionHasNoErrors();
        $this->post(route('attachments.store'), $data + ['file' => UploadedFile::fake()->create('DEMO-too-large.xls', 15 * 1024 + 1, 'application/vnd.ms-excel')])
            ->assertSessionHasErrors('file');
        $this->assertSame(1, $this->task->attachments()->count());
    }
}
