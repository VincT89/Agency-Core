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
            if (!Schema::hasColumn('marketing_campaign_post_publications', 'marketing_campaign_post_version_id')) {
                $table->foreignId('marketing_campaign_post_version_id')
                    ->nullable()
                    ->after('marketing_campaign_post_id')
                    ->constrained('marketing_campaign_post_versions', 'id', 'mcp_pub_version_id_fk')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            if (Schema::hasColumn('marketing_campaign_post_publications', 'marketing_campaign_post_version_id')) {
                $table->dropForeign('mcp_pub_version_id_fk');
                $table->dropColumn('marketing_campaign_post_version_id');
            }
        });
    }
};
