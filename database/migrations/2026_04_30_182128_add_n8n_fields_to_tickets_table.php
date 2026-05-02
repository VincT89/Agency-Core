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
            $table->string('n8n_execution_id')->nullable()->index();
            $table->foreignId('marketing_project_id')->nullable()->constrained('marketing_projects')->nullOnDelete();
            $table->foreignId('social_post_id')->nullable()->constrained('social_posts')->nullOnDelete();
            $table->string('source')->default('system');
            $table->json('context')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['marketing_project_id']);
            $table->dropForeign(['social_post_id']);
            $table->dropColumn(['n8n_execution_id', 'marketing_project_id', 'social_post_id', 'source', 'context']);
        });
    }
};
