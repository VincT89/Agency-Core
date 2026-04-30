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
        Schema::table('marketing_project_media', function (Blueprint $table) {
            $table->unique(['marketing_project_id', 'checksum'], 'mp_media_project_checksum_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_project_media', function (Blueprint $table) {
            $table->dropUnique('mp_media_project_checksum_unique');
        });
    }
};
