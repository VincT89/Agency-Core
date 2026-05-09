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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('nextcloud_folder_name', 100)->nullable()->unique()->after('slug');
            $table->string('nextcloud_photos_path', 255)->nullable()->after('nextcloud_folder_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['nextcloud_folder_name', 'nextcloud_photos_path']);
        });
    }
};
