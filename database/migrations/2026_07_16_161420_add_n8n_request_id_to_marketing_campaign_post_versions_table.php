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
        Schema::table('marketing_campaign_post_versions', function (Blueprint $table) {
            $table->string('n8n_request_id')->nullable()->index()->after('marketing_campaign_post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_post_versions', function (Blueprint $table) {
            $table->dropColumn('n8n_request_id');
        });
    }
};
