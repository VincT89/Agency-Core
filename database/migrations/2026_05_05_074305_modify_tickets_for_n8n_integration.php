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
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->dropIndex('tickets_n8n_execution_id_index');
            $table->renameColumn('n8n_execution_id', 'external_id');
            $table->timestamp('received_at')->nullable();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['source', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn('received_at');
            $table->renameColumn('external_id', 'n8n_execution_id');
            $table->index('n8n_execution_id', 'tickets_n8n_execution_id_index');

            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
