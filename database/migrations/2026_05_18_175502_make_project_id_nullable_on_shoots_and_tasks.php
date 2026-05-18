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
            // Drop existing foreign key
            $table->dropForeign(['project_id']);
            // Make column nullable
            $table->unsignedBigInteger('project_id')->nullable()->change();
            // Re-add foreign key with nullOnDelete instead of cascadeOnDelete
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            // Drop existing foreign key
            $table->dropForeign(['project_id']);
            // Make column nullable
            $table->unsignedBigInteger('project_id')->nullable()->change();
            // Re-add foreign key with cascadeOnDelete (or nullOnDelete?)
            // Tasks usually delete if project deletes, but if project_id is nullable, we should probably set nullOnDelete if the project is deleted, OR cascadeOnDelete if it was attached to a project.
            // If project_id is nullable, deleting the project should probably just delete the project's tasks, but if we change to nullOnDelete it will keep the tasks.
            // Since previously it was cascadeOnDelete, if a project is deleted we should probably still cascade delete its tasks to avoid DB bloat, but if it's null it won't be deleted.
            // Let's use cascadeOnDelete for tasks since that was the original behavior: $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // Wait, if we use cascadeOnDelete, we can just say:
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
