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
            $table->string('external_task_id')->nullable()->after('external_container_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->dropColumn('external_task_id');
        });
    }
};
