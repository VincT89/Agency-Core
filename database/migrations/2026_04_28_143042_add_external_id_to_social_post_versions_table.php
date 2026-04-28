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
        Schema::table('social_post_versions', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('social_post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_post_versions', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
    }
};
