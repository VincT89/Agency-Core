<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marketing_campaign_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            
            $table->string('content_type')->default('post');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            
            $table->string('status')->default('draft');
            $table->boolean('ai_analysis_enabled')->default(true);
            
            $table->string('n8n_request_id')->nullable()->unique();
            $table->timestamp('submitted_to_n8n_at')->nullable();
            
            $table->string('media_path')->nullable();
            $table->string('media_original_name')->nullable();
            $table->string('media_mime')->nullable();
            
            $table->json('n8n_payload')->nullable();
            
            $table->timestamps();
            
            $table->index(['marketing_campaign_id', 'scheduled_date'], 'mcp_camp_date_idx');
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_posts');
    }
};
