<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_client_id')->constrained('chatbot_clients')->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('marketing_campaign_id')->unique();
            $table->string('name');
            $table->string('status', 50);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['chatbot_client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_marketing_campaigns');
    }
};
