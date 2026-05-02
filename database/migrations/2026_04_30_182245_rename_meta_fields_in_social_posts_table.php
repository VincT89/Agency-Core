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
        Schema::table('social_posts', function (Blueprint $table) {
            $table->renameColumn('meta_post_id', 'external_post_id');
            $table->renameColumn('meta_permalink', 'external_post_url');
            $table->string('published_platform')->nullable()->after('publication_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->renameColumn('external_post_id', 'meta_post_id');
            $table->renameColumn('external_post_url', 'meta_permalink');
            $table->dropColumn('published_platform');
        });
    }
};
