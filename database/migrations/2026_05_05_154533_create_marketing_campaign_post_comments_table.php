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
        Schema::create('marketing_campaign_post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_post_id');
            $table->foreign('marketing_campaign_post_id', 'mcpc_post_id_fk')
                  ->references('id')
                  ->on('marketing_campaign_posts')
                  ->cascadeOnDelete();
                  
            $table->foreignId('marketing_campaign_post_version_id')->nullable();
            $table->foreign('marketing_campaign_post_version_id', 'mcpc_version_id_fk')
                  ->references('id')
                  ->on('marketing_campaign_post_versions')
                  ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
        
            $table->text('body');
            $table->string('visibility')->default('internal');
            $table->string('type')->default('comment');
        
            $table->timestamps();
        
            $table->index(['marketing_campaign_post_id', 'visibility'], 'mcpc_post_visibility_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_post_comments');
    }
};
