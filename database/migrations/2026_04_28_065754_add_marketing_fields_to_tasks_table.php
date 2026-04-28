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
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('marketing_project_id')->after('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('social_post_id')->after('marketing_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('editorial_plan_slot_id')->after('social_post_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['marketing_project_id']);
            $table->dropForeign(['social_post_id']);
            $table->dropForeign(['editorial_plan_slot_id']);
            $table->dropColumn([
                'marketing_project_id',
                'social_post_id',
                'editorial_plan_slot_id',
            ]);
        });
    }
};
