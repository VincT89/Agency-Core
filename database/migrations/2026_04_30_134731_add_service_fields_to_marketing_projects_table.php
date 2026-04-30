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
        Schema::table('marketing_projects', function (Blueprint $table) {
            $table->string('service_type')->default('other')->after('type');
            $table->string('campaign_structure')->default('one_shot')->after('service_type');
            $table->json('service_options')->nullable()->after('campaign_structure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_projects', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'campaign_structure', 'service_options']);
        });
    }
};
