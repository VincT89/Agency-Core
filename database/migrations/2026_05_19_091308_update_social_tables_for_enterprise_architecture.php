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
        // 1. Create social_oauth_sessions table
        Schema::create('social_oauth_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->unsignedBigInteger('social_connection_request_id');
            $table->foreign('social_connection_request_id', 'fk_oauth_sess_request_id')
                  ->references('id')->on('social_connection_requests')
                  ->cascadeOnDelete();

            $table->text('long_lived_token')->nullable();
            
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            
            $table->timestamps();
        });

        // 2. Create temporary_media_uploads table
        Schema::create('temporary_media_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 1000);
            $table->string('temp_path', 1000);
            $table->string('hash', 64)->nullable()->index();
            
            $table->unsignedBigInteger('marketing_campaign_post_id')->nullable();
            $table->foreign('marketing_campaign_post_id', 'fk_temp_media_post_id')
                  ->references('id')->on('marketing_campaign_posts')
                  ->nullOnDelete();

            $table->string('cleanup_status')->default('pending'); // pending, cleaned
            $table->uuid('correlation_id')->nullable()->index();
            
            $table->timestamps();
        });

        // 3. Update social_connection_requests
        Schema::table('social_connection_requests', function (Blueprint $table) {
            $table->uuid('correlation_id')->nullable()->index()->after('id');
        });

        // 4. Update marketing_campaign_post_publications
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->uuid('correlation_id')->nullable()->index()->after('id');
            
            $table->string('meta_processing_state')->nullable()->after('status');
            $table->json('provider_state_payload')->nullable()->after('response_snapshot');
            $table->json('provider_last_response')->nullable()->after('provider_state_payload');
        });

        // 5. Update client_social_accounts
        Schema::table('client_social_accounts', function (Blueprint $table) {
            $table->json('publishing_capabilities')->nullable()->after('is_ready_to_publish');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_social_accounts', function (Blueprint $table) {
            $table->dropColumn('publishing_capabilities');
        });

        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->dropColumn(['correlation_id', 'meta_processing_state', 'provider_state_payload', 'provider_last_response']);
        });

        Schema::table('social_connection_requests', function (Blueprint $table) {
            $table->dropColumn('correlation_id');
        });

        Schema::dropIfExists('temporary_media_uploads');
        Schema::dropIfExists('social_oauth_sessions');
    }
};
