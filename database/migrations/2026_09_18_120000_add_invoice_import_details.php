<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('import_fingerprint', 64)->nullable()->unique();
            $table->string('import_source', 20)->nullable();
            $table->json('import_metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['import_fingerprint']);
            $table->dropColumn(['import_fingerprint', 'import_source', 'import_metadata']);
        });
    }
};
