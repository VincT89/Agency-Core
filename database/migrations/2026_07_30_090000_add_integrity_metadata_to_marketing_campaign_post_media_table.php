<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_campaign_post_media', function (Blueprint $table) {
            $table->unsignedBigInteger('source_size_bytes')->nullable()->after('mime_type');
            $table->char('sha256', 64)->nullable()->after('source_size_bytes');
            $table->string('nextcloud_etag')->nullable()->after('nextcloud_file_id');

            $table->index('sha256', 'mcp_media_sha256_index');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_campaign_post_media', function (Blueprint $table) {
            $table->dropIndex('mcp_media_sha256_index');
            $table->dropColumn([
                'source_size_bytes',
                'sha256',
                'nextcloud_etag',
            ]);
        });
    }
};
