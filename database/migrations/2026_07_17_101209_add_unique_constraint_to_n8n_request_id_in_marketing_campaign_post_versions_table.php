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
        Schema::table('marketing_campaign_post_versions', function (Blueprint $table) {
            $table->dropIndex('marketing_campaign_post_versions_n8n_request_id_index');
            $table->unique('n8n_request_id', 'mcpv_n8n_request_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_post_versions', function (Blueprint $table) {
            $table->dropUnique('mcpv_n8n_request_unique');
            $table->index('n8n_request_id', 'marketing_campaign_post_versions_n8n_request_id_index');
        });
    }
};
