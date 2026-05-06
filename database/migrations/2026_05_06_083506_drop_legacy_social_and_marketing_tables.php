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
        // 1. Drop Foreign Keys & Columns in active tables
        if (Schema::hasColumn('tickets', 'marketing_project_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropForeign(['marketing_project_id']);
                $table->dropColumn('marketing_project_id');
            });
        }
        if (Schema::hasColumn('tickets', 'social_post_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropForeign(['social_post_id']);
                $table->dropColumn('social_post_id');
            });
        }

        if (Schema::hasColumn('tasks', 'marketing_project_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['marketing_project_id']);
                $table->dropColumn('marketing_project_id');
            });
        }
        if (Schema::hasColumn('tasks', 'social_post_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['social_post_id']);
                $table->dropColumn('social_post_id');
            });
        }
        if (Schema::hasColumn('tasks', 'editorial_plan_slot_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['editorial_plan_slot_id']);
                $table->dropColumn('editorial_plan_slot_id');
            });
        }

        if (Schema::hasColumn('shoots', 'marketing_project_id')) {
            Schema::table('shoots', function (Blueprint $table) {
                $table->dropForeign(['marketing_project_id']);
                $table->dropColumn('marketing_project_id');
            });
        }

        // 2. Drop Legacy Tables
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('social_post_review_tokens');
        Schema::dropIfExists('social_post_comments');
        Schema::dropIfExists('social_post_versions');
        Schema::dropIfExists('editorial_slots');
        Schema::dropIfExists('editorial_plan_slots');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('editorial_plans');
        Schema::dropIfExists('marketing_project_media');
        Schema::dropIfExists('marketing_projects');
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
