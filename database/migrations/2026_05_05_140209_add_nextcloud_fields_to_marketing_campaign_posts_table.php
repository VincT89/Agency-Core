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
            $table->string('media_source')->default('local')->after('media_mime');
            $table->string('nextcloud_path')->nullable()->after('media_source');
            $table->string('nextcloud_share_url')->nullable()->after('nextcloud_path');
            $table->string('nextcloud_file_id')->nullable()->after('nextcloud_share_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            $table->dropColumn([
                'media_source',
                'nextcloud_path',
                'nextcloud_share_url',
                'nextcloud_file_id'
            ]);
        });
    }
};
