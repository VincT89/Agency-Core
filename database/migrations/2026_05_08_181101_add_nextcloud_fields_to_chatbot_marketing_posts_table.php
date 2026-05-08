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
        Schema::table('chatbot_marketing_posts', function (Blueprint $table) {
            $table->string('nextcloud_file_id')->nullable();
            $table->string('nextcloud_path', 1024)->nullable();
            $table->text('nextcloud_share_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chatbot_marketing_posts', function (Blueprint $table) {
            $table->dropColumn(['nextcloud_file_id', 'nextcloud_path', 'nextcloud_share_url']);
        });
    }
};
