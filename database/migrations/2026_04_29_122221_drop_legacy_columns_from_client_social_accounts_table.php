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
        Schema::table('client_social_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'facebook_page_url',
                'instagram_profile_url',
                'meta_business_manager_id',
                'has_agency_access',
                'facebook_page_id',
                'instagram_business_account_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_social_accounts', function (Blueprint $table) {
            $table->string('provider')->default('meta');
            $table->string('facebook_page_url')->nullable();
            $table->string('instagram_profile_url')->nullable();
            $table->string('meta_business_manager_id')->nullable();
            $table->boolean('has_agency_access')->default(false);
            $table->string('facebook_page_id')->nullable();
            $table->string('instagram_business_account_id')->nullable();
            
            $table->unique(['client_id', 'provider']);
        });
    }
};
