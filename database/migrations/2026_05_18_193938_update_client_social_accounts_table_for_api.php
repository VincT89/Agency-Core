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
            $table->string('provider_account_id')->nullable()->after('platform');
            $table->string('provider_account_name')->nullable()->after('provider_account_id');

            $table->string('facebook_page_id')->nullable()->after('business_manager_id');
            $table->string('instagram_business_account_id')->nullable()->after('facebook_page_id');
            $table->string('tiktok_open_id')->nullable()->after('tiktok_account_id');

            $table->json('scopes')->nullable()->after('refresh_token');
            $table->json('api_metadata')->nullable()->after('scopes');

            $table->timestamp('connected_at')->nullable()->after('token_expires_at');
            $table->timestamp('last_api_check_at')->nullable()->after('connected_at');
            $table->text('last_api_error')->nullable()->after('last_api_check_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_social_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'provider_account_id',
                'provider_account_name',
                'facebook_page_id',
                'instagram_business_account_id',
                'tiktok_open_id',
                'scopes',
                'api_metadata',
                'connected_at',
                'last_api_check_at',
                'last_api_error'
            ]);
        });
    }
};
