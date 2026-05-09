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
        Schema::create('marketing_campaign_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_post_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('local');
            $table->string('media_type')->default('image');
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->text('url')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('original_name')->nullable();
            $table->string('nextcloud_path')->nullable();
            $table->text('nextcloud_share_url')->nullable();
            $table->string('nextcloud_file_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_post_media');
    }
};
