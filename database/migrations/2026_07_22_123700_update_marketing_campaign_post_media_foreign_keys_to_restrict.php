<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketing_campaign_post_version_media', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('mcp_version_media_media_id_fk');
            } else {
                $table->dropForeign(['marketing_campaign_post_media_id']);
            }
        });

        Schema::table('marketing_campaign_post_media', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('marketing_campaign_post_media_marketing_campaign_post_id_foreign');
            } else {
                $table->dropForeign(['marketing_campaign_post_id']);
            }
        });

        // 2. Add new constraints with restrictOnDelete
        Schema::table('marketing_campaign_post_version_media', function (Blueprint $table) {
            $table->foreign('marketing_campaign_post_media_id', 'mcp_version_media_media_id_fk')
                  ->references('id')->on('marketing_campaign_post_media')
                  ->restrictOnDelete();
        });

        Schema::table('marketing_campaign_post_media', function (Blueprint $table) {
            $table->foreign('marketing_campaign_post_id', 'marketing_campaign_post_media_marketing_campaign_post_id_foreign')
                  ->references('id')->on('marketing_campaign_posts')
                  ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_post_version_media', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('mcp_version_media_media_id_fk');
            } else {
                $table->dropForeign(['marketing_campaign_post_media_id']);
            }
        });

        Schema::table('marketing_campaign_post_media', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('marketing_campaign_post_media_marketing_campaign_post_id_foreign');
            } else {
                $table->dropForeign(['marketing_campaign_post_id']);
            }
        });

        Schema::table('marketing_campaign_post_version_media', function (Blueprint $table) {
            $table->foreign('marketing_campaign_post_media_id', 'mcp_version_media_media_id_fk')
                  ->references('id')->on('marketing_campaign_post_media')
                  ->cascadeOnDelete();
        });

        Schema::table('marketing_campaign_post_media', function (Blueprint $table) {
            $table->foreign('marketing_campaign_post_id', 'marketing_campaign_post_media_marketing_campaign_post_id_foreign')
                  ->references('id')->on('marketing_campaign_posts')
                  ->cascadeOnDelete();
        });
    }
};
