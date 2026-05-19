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
        Schema::create('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('marketing_campaign_post_id');
            $table->foreign('marketing_campaign_post_id', 'fk_mcp_pub_post_id')
                  ->references('id')->on('marketing_campaign_posts')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('client_social_account_id')->nullable();
            $table->foreign('client_social_account_id', 'fk_mcp_pub_social_id')
                  ->references('id')->on('client_social_accounts')
                  ->nullOnDelete();

            $table->string('platform');
            $table->string('status')->default('pending');
            // pending, publishing, published, failed

            $table->string('external_post_id')->nullable();
            $table->string('external_container_id')->nullable();
            $table->string('external_permalink')->nullable();

            $table->json('payload_snapshot')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_post_publications');
    }
};
