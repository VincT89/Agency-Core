<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_marketing_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_client_id')->constrained('chatbot_clients')->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('marketing_campaign_id');
            $table->unsignedBigInteger('marketing_campaign_post_id')->unique();
            $table->string('campaign_name')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 50);
            $table->string('media_path')->nullable();
            $table->string('media_source', 50)->nullable();
            $table->date('scheduled_date')->nullable()->index();
            $table->time('scheduled_time')->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['chatbot_client_id', 'source_created_at'], 'idx_cmp_client_created');
            $table->index(['chatbot_client_id', 'status'], 'idx_cmp_client_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_marketing_posts');
    }
};
