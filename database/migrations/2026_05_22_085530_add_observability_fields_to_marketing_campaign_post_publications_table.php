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
            $table->timestamp('publishing_started_at')->nullable()->after('status');
            $table->timestamp('stale_deadline_at')->nullable()->after('publishing_started_at');
            $table->integer('attempt_count')->default(0)->after('stale_deadline_at');
            $table->integer('poll_count')->default(0)->after('attempt_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->dropColumn(['publishing_started_at', 'stale_deadline_at', 'attempt_count', 'poll_count']);
        });
    }
};
