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
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->foreignId('retry_of_publication_id')
                  ->nullable()
                  ->constrained('marketing_campaign_post_publications', 'id', 'fk_mcp_pub_retry_id')
                  ->nullOnDelete();
        });
        
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->unique(['idempotency_key', 'attempt_count'], 'uk_idempotency_attempt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->dropForeign('fk_mcp_pub_retry_id');
            $table->dropColumn('retry_of_publication_id');
            $table->dropUnique('uk_idempotency_attempt');
            $table->unique(['idempotency_key']);
        });
    }
};
