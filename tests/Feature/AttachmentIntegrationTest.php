<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('attachments');
    }

    public function test_user_can_upload_and_download_attachment_to_authorized_entity(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);
        
        $client = Client::create([
            'name' => 'Acme Srl',
            'slug' => 'acme-srl',
            'status' => 'active',
        ]);

        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Project Alpha',
            'slug' => 'project-alpha',
            'status' => 'active',
        ]);
        
        $project->users()->attach($manager, ['role' => 'lead', 'assignment_status' => 'active', 'assigned_at' => now()]);
        
        $ticket = Ticket::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'title' => 'Initial Ticket',
            'status' => 'new',
            'priority' => 'low',
            'type' => 'request',
        ]);

        $file = UploadedFile::fake()->image('document.jpg');

        $response = $this->actingAs($manager)->post(route('attachments.store'), [
            'attachable_type' => 'ticket',
            'attachable_id' => $ticket->id,
            'file' => $file,
            'type' => 'document',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Ticket::class,
            'attachable_id' => $ticket->id,
            'original_name' => 'document.jpg',
            'uploaded_by' => $manager->id,
        ]);

        $attachment = Attachment::first();

        // Download Test
        $downloadResponse = $this->actingAs($manager)->get(route('attachments.download', $attachment));
        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('Content-Disposition', 'attachment; filename=document.jpg');
    }

    public function test_user_cannot_upload_to_unauthorized_entity(): void
    {
        $operativo = User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]);
        
        $client = Client::create([
            'name' => 'Altro Cliente',
            'slug' => 'altro-cliente',
            'status' => 'active',
        ]);

        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Project Alpha',
            'slug' => 'project-alpha',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create('secret.pdf', 100, 'application/pdf');

        $response = $this->actingAs($operativo)->post(route('attachments.store'), [
            'attachable_type' => 'project',
            'attachable_id' => $project->id,
            'file' => $file,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('attachments', [
            'attachable_id' => $project->id,
        ]);
    }

    public function test_upload_fails_on_unsupported_attachable_type(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);

        $client = Client::create([
            'name' => 'Acme Srl',
            'slug' => 'acme-srl',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create('memo.txt', 10, 'text/plain');

        $response = $this->actingAs($admin)->post(route('attachments.store'), [
            'attachable_type' => 'dashboard', // Not supported
            'attachable_id' => $client->id,
            'file' => $file,
        ]);

        // It fails authorization immediately because 'dashboard' is not in ATTACHABLE_MAP
        $response->assertForbidden();
    }

    public function test_operative_out_of_finance_scope_cannot_download_invoice_attachment(): void
    {
        $developer = User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]);
        $financeUser = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);
        
        $client = Client::create([
            'name' => 'Acme Srl',
            'slug' => 'acme-srl',
            'status' => 'active',
        ]);

        $project2 = Project::create([
            'client_id' => $client->id,
            'name' => 'Project Beta',
            'slug' => 'project-beta',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'project_id' => $project2->id,
            'created_by' => $financeUser->id,
            'number' => 'FAT-002',
            'issue_date' => now()->toDateString(),
            'status' => 'issued',
            'currency' => 'EUR',
            'subtotal' => 100,
            'tax_amount' => 22,
            'total' => 122,
            'paid_total' => 0,
        ]);
        
        $attachment = $invoice->attachments()->create([
            'uploaded_by' => $financeUser->id,
            'disk' => 'attachments',
            'directory' => 'invoice/'.$invoice->id,
            'path' => 'invoice/'.$invoice->id.'/fake.pdf',
            'original_name' => 'fake.pdf',
            'stored_name' => 'fake.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1024,
        ]);

        // Developer tries to download attachment of Invoice on Project 2
        $response = $this->actingAs($developer)->get(route('attachments.download', $attachment));
        
        $response->assertForbidden();
    }

    public function test_user_can_delete_own_attachment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $client = Client::create([
            'name' => 'Acme Srl',
            'slug' => 'acme-srl',
            'status' => 'active',
        ]);
        
        $attachment = $client->attachments()->create([
            'uploaded_by' => $admin->id,
            'disk' => 'attachments',
            'directory' => 'client/'.$client->id,
            'path' => 'client/'.$client->id.'/dummy.txt',
            'original_name' => 'dummy.txt',
            'stored_name' => 'dummy.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 123,
        ]);

        Storage::fake('attachments')->put($attachment->path, 'content');

        $response = $this->actingAs($admin)->delete(route('attachments.destroy', $attachment));

        $response->assertRedirect();
        $this->assertModelMissing($attachment);
        Storage::disk('attachments')->assertMissing($attachment->path);
    }

    public function test_direct_url_access_denied_for_out_of_scope_attachment(): void
    {
        $hackerOperativo = User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]);
        $victimAdmin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        
        $client = Client::create([
            'name' => 'Victim Client',
            'slug' => 'victim-client',
            'status' => 'active',
        ]);

        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Secret Project',
            'slug' => 'secret-project',
            'status' => 'active',
        ]);
        // $hackerOperativo is NOT added to $project!

        $task = \App\Models\Task::create([
            'project_id' => $project->id,
            'created_by' => $victimAdmin->id,
            'title' => 'Secret Task',
            'status' => 'todo',
        ]);

        $attachment = $task->attachments()->create([
            'uploaded_by' => $victimAdmin->id,
            'disk' => 'attachments',
            'directory' => 'task/'.$task->id,
            'path' => 'task/'.$task->id.'/secret_password.txt',
            'original_name' => 'secret_password.txt',
            'stored_name' => 'secret_password.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 123,
        ]);

        // Attacco diretto all'URL da parte dell'operativo senza permesso
        $response = $this->actingAs($hackerOperativo)->get(route('attachments.download', $attachment));
        
        $response->assertForbidden();
    }
}
