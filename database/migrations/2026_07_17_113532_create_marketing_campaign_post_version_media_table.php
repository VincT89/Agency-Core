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
        Schema::create('marketing_campaign_post_version_media', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('marketing_campaign_post_version_id')
                ->constrained('marketing_campaign_post_versions', 'id', 'mcp_version_media_version_id_fk')
                ->cascadeOnDelete();
                
            $table->foreignId('marketing_campaign_post_media_id')
                ->constrained('marketing_campaign_post_media', 'id', 'mcp_version_media_media_id_fk')
                ->cascadeOnDelete();
                
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();

            $table->unique(
                ['marketing_campaign_post_version_id', 'marketing_campaign_post_media_id'],
                'mcp_version_media_unique'
            );
            
            $table->index(
                ['marketing_campaign_post_version_id', 'sort_order'],
                'mcp_version_media_sort_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_post_version_media');
    }
};
