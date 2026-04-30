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
        Schema::table('shoots', function (Blueprint $table) {
            $table->foreignId('marketing_project_id')
                ->nullable()
                ->after('project_id')
                ->constrained('marketing_projects')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            $table->dropForeign(['marketing_project_id']);
            $table->dropColumn('marketing_project_id');
        });
    }
};
