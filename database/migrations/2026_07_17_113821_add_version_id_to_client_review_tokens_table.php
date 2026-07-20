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
        Schema::table('client_review_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('client_review_tokens', 'marketing_campaign_post_version_id')) {
                $table->foreignId('marketing_campaign_post_version_id')
                    ->nullable()
                    ->after('reviewable_id')
                    ->constrained('marketing_campaign_post_versions')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_review_tokens', function (Blueprint $table) {
            $table->dropForeign(['marketing_campaign_post_version_id']);
            $table->dropColumn('marketing_campaign_post_version_id');
        });
    }
};
