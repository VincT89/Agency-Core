<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->string('publication_mode')
                ->default('manual')
                ->after('status');
            $table->boolean('client_review_required')
                ->default(true)
                ->after('publication_mode');

            $table->index(
                ['publication_mode', 'status'],
                'marketing_campaigns_publication_mode_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropIndex(
                'marketing_campaigns_publication_mode_status_index'
            );
            $table->dropColumn([
                'publication_mode',
                'client_review_required',
            ]);
        });
    }
};
