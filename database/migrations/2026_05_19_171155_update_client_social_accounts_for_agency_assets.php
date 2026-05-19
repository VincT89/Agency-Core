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
        Schema::table('client_social_accounts', function (Blueprint $table) {
            $table->foreignId('agency_social_asset_id')->nullable()->after('client_id')->constrained('agency_social_assets')->nullOnDelete();
            $table->string('connection_strategy')->nullable()->after('platform'); // agency_oauth, manual_token_config
            
            // Audit fields
            $table->foreignId('assignment_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assignment_changed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_social_accounts', function (Blueprint $table) {
            $table->dropForeign(['agency_social_asset_id']);
            $table->dropForeign(['assignment_changed_by']);
            $table->dropColumn([
                'agency_social_asset_id',
                'connection_strategy',
                'assignment_changed_by',
                'assignment_changed_at'
            ]);
        });
    }
};
