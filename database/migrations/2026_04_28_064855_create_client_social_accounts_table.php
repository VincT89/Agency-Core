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
        Schema::create('client_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('meta');
            $table->string('facebook_page_url')->nullable();
            $table->string('instagram_profile_url')->nullable();
            $table->string('meta_business_manager_id')->nullable();
            
            $table->boolean('has_agency_access')->default(false);
            $table->string('access_status')->default('missing');
            
            $table->string('facebook_page_id')->nullable();
            $table->string('instagram_business_account_id')->nullable();
            $table->text('access_token')->nullable();
            $table->datetime('token_expires_at')->nullable();
            $table->string('api_status')->default('not_configured');
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['client_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_social_accounts');
    }
};
