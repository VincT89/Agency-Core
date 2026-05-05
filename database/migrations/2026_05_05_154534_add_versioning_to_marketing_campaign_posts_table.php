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
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('n8n_payload');
            $table->foreign('current_version_id', 'mcp_current_version_fk')
                  ->references('id')
                  ->on('marketing_campaign_post_versions')
                  ->nullOnDelete();
        
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('n8n_completed_at')->nullable();
            $table->text('n8n_error')->nullable();
        
            $table->timestamp('sent_to_client_at')->nullable();
            $table->timestamp('client_approved_at')->nullable();
        
            $table->index(['status', 'scheduled_date'], 'mcp_status_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
            $table->dropIndex(['status', 'scheduled_date']);
            $table->dropColumn([
                'current_version_id',
                'generated_at',
                'n8n_completed_at',
                'n8n_error',
                'sent_to_client_at',
                'client_approved_at'
            ]);
        });
    }
};
