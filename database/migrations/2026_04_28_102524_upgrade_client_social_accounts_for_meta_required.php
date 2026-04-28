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
            $table->dropUnique(['client_id', 'provider']);
            
            // Aggiungo prima i campi
            $table->string('platform')->nullable()->after('provider');
            $table->string('account_name')->nullable();
            $table->string('account_url')->nullable();
            $table->string('username')->nullable();
            $table->boolean('account_exists')->default(false);
            
            $table->string('access_method')->default('unknown');
            $table->boolean('is_ready_to_publish')->default(false);
            
            $table->timestamp('access_verified_at')->nullable();
            $table->foreignId('access_verified_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('business_manager_id')->nullable();
            $table->string('business_center_id')->nullable();
            
            $table->string('tiktok_account_id')->nullable();
            $table->string('credential_location')->nullable();
            
            $table->string('api_provider')->nullable();
            $table->text('refresh_token')->nullable();
            
            $table->text('api_notes')->nullable();
        });

        // Data backfill
        $accounts = \Illuminate\Support\Facades\DB::table('client_social_accounts')->where('provider', 'meta')->get();
        
        foreach ($accounts as $account) {
            // Update the existing record to become "facebook"
            \Illuminate\Support\Facades\DB::table('client_social_accounts')
                ->where('id', $account->id)
                ->update([
                    'platform' => 'facebook',
                    'account_exists' => true,
                    'account_url' => $account->facebook_page_url,
                    'facebook_page_id' => $account->facebook_page_id,
                    'access_method' => 'meta_business',
                    'access_status' => 'ready_to_publish',
                    'is_ready_to_publish' => true,
                    'business_manager_id' => $account->meta_business_manager_id,
                ]);

            // Create a new record for "instagram" using the same Meta data
            \Illuminate\Support\Facades\DB::table('client_social_accounts')->insert([
                'client_id' => $account->client_id,
                'platform' => 'instagram',
                'account_exists' => true,
                'account_url' => $account->instagram_profile_url,
                'instagram_business_account_id' => $account->instagram_business_account_id,
                'access_method' => 'meta_business',
                'access_status' => 'ready_to_publish',
                'is_ready_to_publish' => true,
                'business_manager_id' => $account->meta_business_manager_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Se esistono duplicati sulla stessa piattaforma, mantieni il più recente ed elimina i vecchi per poter creare l'indice unico
        $duplicates = \Illuminate\Support\Facades\DB::table('client_social_accounts')
            ->select('client_id', 'platform')
            ->whereNotNull('platform')
            ->groupBy('client_id', 'platform')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = \Illuminate\Support\Facades\DB::table('client_social_accounts')
                ->where('client_id', $duplicate->client_id)
                ->where('platform', $duplicate->platform)
                ->orderBy('id', 'desc')
                ->pluck('id')
                ->toArray();
            
            array_shift($ids); // Keep the first one (most recent)
            \Illuminate\Support\Facades\DB::table('client_social_accounts')->whereIn('id', $ids)->delete();
        }

        Schema::table('client_social_accounts', function (Blueprint $table) {
            $table->unique(['client_id', 'platform'], 'csa_client_platform_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_social_accounts', function (Blueprint $table) {
            $table->dropUnique('csa_client_platform_unique');
            $table->dropColumn([
                'platform', 'account_name', 'account_url', 'username', 'account_exists',
                'access_method', 'is_ready_to_publish',
                'access_verified_at', 'access_verified_by',
                'business_manager_id', 'business_center_id',
                'tiktok_account_id', 'credential_location', 'api_provider', 
                'refresh_token', 'api_notes'
            ]);
            $table->unique(['client_id', 'provider']);
        });
    }
};
