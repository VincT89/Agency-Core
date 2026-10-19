<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('system_key', 40)->nullable()->unique();
        });

        /** An existing project with the same name must keep its identity and client. */
        $slug = 'interventi-e-vendite';
        for ($suffix = 2; DB::table('projects')->where('slug', $slug)->exists(); $suffix++) {
            $slug = 'interventi-e-vendite-'.$suffix;
        }
        DB::table('projects')->insert([
            'system_key' => 'invoice_container', 'client_id' => null,
            'name' => 'Interventi e vendite', 'slug' => $slug, 'status' => 'active',
            'description' => 'Contenitore condiviso per interventi occasionali, lavori veloci e vendite. Ogni fattura mantiene il proprio cliente.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        /** Preserve the project and its associations when rolling back the feature. */
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['system_key']);
            $table->dropColumn('system_key');
        });
    }
};
