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
            $table->renameColumn('n8n_payload', 'approved_payload_snapshot');
        });
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            $table->string('n8n_payload_hash')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            $table->dropColumn('n8n_payload_hash');
        });
        Schema::table('marketing_campaign_posts', function (Blueprint $table) {
            $table->renameColumn('approved_payload_snapshot', 'n8n_payload');
        });
    }
};
