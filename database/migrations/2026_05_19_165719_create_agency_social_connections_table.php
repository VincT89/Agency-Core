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
        Schema::create('agency_social_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index(); // es. 'facebook'
            $table->string('provider_user_id')->nullable();
            $table->string('provider_user_name')->nullable();
            
            // Tokens
            $table->text('access_token')->nullable(); // user long-lived token
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            
            // Lifecycle Token
            $table->timestamp('last_token_refresh_at')->nullable();
            $table->text('token_refresh_error')->nullable();
            $table->boolean('requires_reauth')->default(false);
            
            $table->json('scopes')->nullable();
            $table->string('status')->default('connected'); // Enum: connected, expired, revoked, permission_missing, sync_failed
            
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->useCurrent();
            $table->timestamp('last_sync_at')->nullable();
            
            $table->timestamp('last_api_check_at')->nullable();
            $table->text('last_api_error')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agency_social_connections');
    }
};
