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
            $table->json('publishing_platforms')->nullable()->default(null)->after('media_mime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            $table->dropColumn('publishing_platforms');
        });
    }
};
