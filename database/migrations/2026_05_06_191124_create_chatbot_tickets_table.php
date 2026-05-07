<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_client_id')->constrained('chatbot_clients')->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('ticket_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 50);
            $table->string('priority', 50)->nullable();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->string('assigned_to_name')->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['chatbot_client_id', 'source_created_at'], 'idx_ct_client_created');
            $table->index(['chatbot_client_id', 'status'], 'idx_ct_client_status');
            $table->index(['chatbot_client_id', 'priority'], 'idx_ct_client_priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_tickets');
    }
};
