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
        Schema::create('marketing_campaign_post_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_post_id');
            $table->foreign('marketing_campaign_post_id', 'mcpv_post_id_fk')
                  ->references('id')
                  ->on('marketing_campaign_posts')
                  ->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        
            $table->unsignedInteger('version_number');
            $table->string('regeneration_type')->default('full');
        
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
        
            $table->string('image_url')->nullable();
            $table->string('image_path')->nullable();
        
            $table->text('prompt_used')->nullable();
            $table->string('external_generation_id')->nullable()->unique();
            $table->string('source')->default('n8n');
            $table->json('raw_payload')->nullable();
        
            $table->timestamps();
        
            $table->unique(['marketing_campaign_post_id', 'version_number'], 'mcpv_post_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_post_versions');
    }
};
