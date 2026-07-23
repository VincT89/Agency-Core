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
        // 1. Drop old constraints
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('marketing_campaign_posts_marketing_campaign_id_foreign');
            } else {
                $table->dropForeign(['marketing_campaign_id']);
            }
        });

        Schema::table('marketing_campaign_post_versions', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('mcpv_post_id_fk');
            } else {
                $table->dropForeign(['marketing_campaign_post_id']);
            }
        });
        
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('fk_mcp_pub_post_id');
            } else {
                $table->dropForeign(['marketing_campaign_post_id']);
            }
        });

        // 2. Add new restrict constraints
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            $table->foreign('marketing_campaign_id')
                  ->references('id')->on('marketing_campaigns')
                  ->restrictOnDelete();
        });

        Schema::table('marketing_campaign_post_versions', function (Blueprint $table) {
            $table->foreign('marketing_campaign_post_id', 'mcpv_post_id_fk')
                  ->references('id')->on('marketing_campaign_posts')
                  ->restrictOnDelete();
        });

        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->foreign('marketing_campaign_post_id', 'fk_mcp_pub_post_id')
                  ->references('id')->on('marketing_campaign_posts')
                  ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('marketing_campaign_posts_marketing_campaign_id_foreign');
            } else {
                $table->dropForeign(['marketing_campaign_id']);
            }
        });

        Schema::table('marketing_campaign_post_versions', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('mcpv_post_id_fk');
            } else {
                $table->dropForeign(['marketing_campaign_post_id']);
            }
        });

        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('fk_mcp_pub_post_id');
            } else {
                $table->dropForeign(['marketing_campaign_post_id']);
            }
        });
        
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            $table->foreign('marketing_campaign_id')
                  ->references('id')->on('marketing_campaigns')
                  ->cascadeOnDelete();
        });

        Schema::table('marketing_campaign_post_versions', function (Blueprint $table) {
            $table->foreign('marketing_campaign_post_id', 'mcpv_post_id_fk')
                  ->references('id')->on('marketing_campaign_posts')
                  ->cascadeOnDelete();
        });

        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->foreign('marketing_campaign_post_id', 'fk_mcp_pub_post_id')
                  ->references('id')->on('marketing_campaign_posts')
                  ->cascadeOnDelete();
        });
    }
};
