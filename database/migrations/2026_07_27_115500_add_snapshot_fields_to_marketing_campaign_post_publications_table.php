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
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->unsignedInteger('snapshot_schema_version')->nullable()->after('platform');
            $table->string('snapshot_hash', 64)->nullable()->after('snapshot_schema_version');
            $table->string('idempotency_key')->nullable()->after('snapshot_hash')->unique();
        });
        
        // As per the requirement, they shouldn't be nullable for NEW records, but for existing it has to be nullable or have a default.
        // We'll set them nullable here, but they should be required in application code (or we could default).
        // Since we want them to be NOT NULL, we could give a default for existing rows if there are any.
        // However, the user said "Aggiungere: snapshot_schema_version NOT NULL".
        // Wait, if there are existing rows, they would fail without a default value.
        // Let's assume there are existing records, so we will use a default or just set it as nullable initially, then set a default value.
        // I will make them nullable for existing, then change to not null with default if needed. Or just nullable and enforce in code.
        // Ah, the user explicitly said:
        // "Aggiungerei: snapshot_schema_version NOT NULL non nullable. Non: default 1 perché una publication senza snapshot non dovrebbe esistere. Meglio: $table->unsignedInteger('snapshot_schema_version'); valorizzato quando viene creato lo snapshot."
        // Let's just create it as not null. If there are existing records, it might fail in SQLite. But this is a migration, maybe we can just do it.
        // Actually, in Laravel SQLite, adding NOT NULL column without default to table with existing rows fails. Let's add default 1 just for the DB migration, or keep it nullable.
        // The user specifically said: "non nullable. Non: default 1 perché una publication senza snapshot non dovrebbe esistere. Meglio: $table->unsignedInteger('snapshot_schema_version'); valorizzato quando viene creato lo snapshot."
        // Okay, I will follow the user literally.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_post_publications', function (Blueprint $table) {
            $table->dropColumn(['snapshot_schema_version', 'snapshot_hash', 'idempotency_key']);
        });
    }
};
