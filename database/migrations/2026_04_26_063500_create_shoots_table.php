<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shoots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photographer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('title');
            $table->string('code')->unique();
            $table->string('location')->nullable();
            
            $table->string('status')->default('draft');
            // draft, waiting_photographer, photographer_rejected, waiting_client, client_rejected, client_confirmed, scheduled, task_created, cancelled
            
            // Reference to the chosen slot, if any
            $table->unsignedBigInteger('selected_slot_id')->nullable();
            
            // Client Confirmation
            $table->string('client_confirmation_status')->nullable();
            $table->timestamp('client_confirmed_at')->nullable();
            $table->string('client_confirmation_channel')->nullable();
            $table->string('whatsapp_message_id')->nullable();
            
            // Connected Entities
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            
            // Notes
            $table->text('internal_notes')->nullable();
            $table->text('client_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shoots');
    }
};
