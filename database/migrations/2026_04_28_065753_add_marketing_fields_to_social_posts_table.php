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
        Schema::table('social_posts', function (Blueprint $table) {
            $table->foreignId('marketing_project_id')->after('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('editorial_plan_id')->after('marketing_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('editorial_plan_slot_id')->after('editorial_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('publication_mode')->nullable();
            $table->datetime('scheduled_publish_at')->nullable();
            $table->string('publication_status')->default('not_ready');
            $table->datetime('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meta_post_id')->nullable();
            $table->string('meta_permalink')->nullable();
            $table->text('publication_error')->nullable();
            $table->integer('publication_attempts')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropForeign(['marketing_project_id']);
            $table->dropForeign(['editorial_plan_id']);
            $table->dropForeign(['editorial_plan_slot_id']);
            $table->dropForeign(['published_by']);
            $table->dropColumn([
                'marketing_project_id',
                'editorial_plan_id',
                'editorial_plan_slot_id',
                'publication_mode',
                'scheduled_publish_at',
                'publication_status',
                'published_at',
                'published_by',
                'meta_post_id',
                'meta_permalink',
                'publication_error',
                'publication_attempts',
            ]);
        });
    }
};
