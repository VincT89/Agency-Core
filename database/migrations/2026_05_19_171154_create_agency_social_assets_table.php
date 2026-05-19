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
        Schema::create('agency_social_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_social_connection_id')->constrained()->cascadeOnDelete();
            
            // Relazioni gerarchiche
            $table->foreignId('parent_asset_id')->nullable()->constrained('agency_social_assets')->nullOnDelete();
            
            $table->string('provider')->index(); // es. 'facebook'
            $table->string('platform')->index(); // es. 'facebook', 'instagram'
            $table->string('asset_type')->index(); // Enum: 'facebook_page', 'instagram_business_account'
            
            // Dettagli Asset
            $table->string('provider_asset_id')->index();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            
            // ID Meta (se applicabili, per compatibilità con il passato)
            $table->string('facebook_page_id')->nullable();
            $table->string('instagram_business_account_id')->nullable();
            
            // Token (Criptato, salvato solo sul root asset come FB Page)
            $table->text('page_access_token')->nullable();
            
            // Page Token Lifecycle
            $table->string('page_token_status')->nullable(); // connected, invalid
            $table->timestamp('page_token_last_validated_at')->nullable();
            $table->text('page_token_error')->nullable();
            
            // Capabilities e Payload
            $table->json('capabilities')->nullable();
            $table->json('raw_payload')->nullable();
            
            // Asset Lifecycle & Status
            $table->string('status')->default('connected'); // connected, expired, revoked, permission_missing, sync_failed
            $table->boolean('is_assignable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            
            // Publishability
            $table->string('publishing_status')->nullable();
            $table->json('publishing_capabilities')->nullable();
            
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agency_social_assets');
    }
};
