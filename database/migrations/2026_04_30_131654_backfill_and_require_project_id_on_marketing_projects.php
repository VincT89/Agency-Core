<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill orphaned marketing projects
        $orphans = DB::table('marketing_projects')->whereNull('project_id')->get();
        foreach ($orphans as $orphan) {
            $projectId = DB::table('projects')->insertGetId([
                'client_id' => $orphan->client_id,
                'name' => 'Commessa Marketing - ' . $orphan->title,
                'description' => 'Commessa generata automaticamente per campagna marketing esistente.',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('marketing_projects')
                ->where('id', $orphan->id)
                ->update(['project_id' => $projectId]);
        }

        // 2. Make project_id required
        Schema::table('marketing_projects', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->foreignId('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_projects', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->foreignId('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }
};
