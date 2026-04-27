<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_routes_do_not_crash_and_deny_non_admin()
    {
        $manager = User::factory()->create(['role' => UserRole::Administration]);
        $response = $this->actingAs($manager)->get('/users');
        $response->assertStatus(403);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $response = $this->actingAs($admin)->get('/users');
        $response->assertStatus(200);
    }

    public function test_dashboard_does_not_require_verified()
    {
        $user = User::factory()->unverified()->create(['role' => UserRole::Developer]);
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_attachment_upload_deny_outside_perimeter()
    {
        Storage::fake('attachments');
        $operativo = User::factory()->create(['role' => UserRole::Developer]);
        
        $client = new Client();
        $client->name = 'Test';
        $client->slug = \Illuminate\Support\Str::uuid();
        $client->status = 'active';
        $client->save();

        $project = new Project();
        $project->name = 'Test';
        $project->slug = \Illuminate\Support\Str::uuid();
        $project->client_id = $client->id;
        $project->status = 'active';
        $project->save();
        
        // Ticket fuori dal perimetro dell'operativo (non assegnato né creatore, né nel suo progetto)
        $ticket = new Ticket();
        $ticket->title = 'Test';
        $ticket->code = \Illuminate\Support\Str::uuid();
        $ticket->status = 'open';
        $ticket->priority = 'low';
        $ticket->project_id = $project->id;
        $ticket->client_id = $client->id;
        $ticket->created_by = User::factory()->create(['role' => UserRole::Admin])->id;
        $ticket->save();

        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->actingAs($operativo)->postJson('/attachments', [
            'attachable_type' => 'ticket',
            'attachable_id' => $ticket->id,
            'file' => $file
        ]);

        $response->assertStatus(403);
    }

    public function test_attachment_download_deny_outside_perimeter()
    {
        Storage::fake('attachments');
        $operativo = User::factory()->create(['role' => UserRole::Developer]);
        
        $client = new Client();
        $client->name = 'Test';
        $client->slug = \Illuminate\Support\Str::uuid();
        $client->status = 'active';
        $client->save();

        $project = new Project();
        $project->name = 'Test';
        $project->slug = \Illuminate\Support\Str::uuid();
        $project->client_id = $client->id;
        $project->status = 'active';
        $project->save();
        
        // Ticket fuori dal perimetro dell'operativo
        $ticket = new Ticket();
        $ticket->title = 'Test';
        $ticket->code = \Illuminate\Support\Str::uuid();
        $ticket->status = 'open';
        $ticket->priority = 'low';
        $ticket->project_id = $project->id;
        $ticket->client_id = $client->id;
        $ticket->created_by = User::factory()->create(['role' => UserRole::Admin])->id;
        $ticket->save();

        $attachment = new Attachment();
        $attachment->attachable_type = Ticket::class;
        $attachment->attachable_id = $ticket->id;
        $attachment->uploaded_by = User::factory()->create()->id;
        $attachment->disk = 'attachments';
        $attachment->directory = 'test';
        $attachment->path = 'fake/path.jpg';
        $attachment->original_name = 'test.jpg';
        $attachment->stored_name = 'test.jpg';
        $attachment->mime_type = 'image/jpeg';
        $attachment->extension = 'jpg';
        $attachment->size = 100;
        $attachment->save();

        $response = $this->actingAs($operativo)->get("/attachments/{$attachment->id}/download");
        $response->assertStatus(403);
    }

    public function test_administration_cannot_view_orphan_ticket()
    {
        $manager = User::factory()->create(['role' => UserRole::Administration]);
        
        $client = new Client();
        $client->name = 'Test';
        $client->slug = \Illuminate\Support\Str::uuid();
        $client->status = 'active';
        $client->save();

        $ticket = new Ticket();
        $ticket->title = 'Test';
        $ticket->code = 'TCK-3';
        $ticket->status = 'open';
        $ticket->priority = 'low';
        $ticket->project_id = null;
        $ticket->client_id = $client->id;
        $ticket->created_by = User::factory()->create(['role' => UserRole::Admin])->id;
        $ticket->save();

        // The policy checks if they are Operative to even see Any Ticket. In show, it checks `canAccessTicket` fallback.
        // Wait, UserRole::Administration shouldn't be able to view tickets.
        $response = $this->actingAs($manager)->get("/tickets/{$ticket->id}");
        $response->assertStatus(403);
    }

    public function test_administration_has_global_access_to_finance()
    {
        $manager = User::factory()->create(['role' => UserRole::Administration]);
        $client = new Client();
        $client->name = 'Test';
        $client->slug = \Illuminate\Support\Str::uuid();
        $client->status = 'active';
        $client->save();
        
        $invoice = new Invoice();
        $invoice->number = 'INV-123';
        $invoice->issue_date = '2024-01-01';
        $invoice->status = 'draft';
        $invoice->currency = 'EUR';
        $invoice->subtotal = 100;
        $invoice->tax_amount = 22;
        $invoice->total = 122;
        $invoice->client_id = $client->id;
        $invoice->project_id = null;
        $invoice->created_by = User::factory()->create(['role' => UserRole::Admin])->id;
        $invoice->save();

        $response = $this->actingAs($manager)->get("/invoices/{$invoice->id}");
        $response->assertStatus(200); // Administration vede TUTTO in ambito finance! 
    }
}
