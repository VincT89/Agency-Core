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
        Schema::table('chatbot_marketing_campaigns', function (Blueprint $table) {
            $table->index(['chatbot_client_id', 'source_created_at'], 'idx_cmc_client_created');
            $table->index(['chatbot_client_id', 'source_updated_at'], 'idx_cmc_client_updated');
            $table->index('synced_at', 'idx_cmc_synced_at');
        });

        Schema::table('chatbot_marketing_posts', function (Blueprint $table) {
            $table->index(['chatbot_client_id', 'source_updated_at'], 'idx_cmp_client_updated');
            $table->index('synced_at', 'idx_cmp_synced_at');
        });

        Schema::table('chatbot_tickets', function (Blueprint $table) {
            $table->index(['chatbot_client_id', 'source_updated_at'], 'idx_ct_client_updated');
            $table->index('synced_at', 'idx_ct_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_marketing_campaigns', function (Blueprint $table) {
            $table->dropIndex('idx_cmc_client_created');
            $table->dropIndex('idx_cmc_client_updated');
            $table->dropIndex('idx_cmc_synced_at');
        });

        Schema::table('chatbot_marketing_posts', function (Blueprint $table) {
            $table->dropIndex('idx_cmp_client_updated');
            $table->dropIndex('idx_cmp_synced_at');
        });

        Schema::table('chatbot_tickets', function (Blueprint $table) {
            $table->dropIndex('idx_ct_client_updated');
            $table->dropIndex('idx_ct_synced_at');
        });
    }
};
