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
        Schema::table('chatbot_marketing_posts', function (Blueprint $table) {
            $table->string('content_type')->nullable()->after('description');
            $table->json('publishing_platforms')->nullable()->after('content_type');
            $table->timestamp('published_at')->nullable()->after('scheduled_time');
        });

        Schema::table('chatbot_tickets', function (Blueprint $table) {
            $table->string('type')->nullable()->after('description');
            $table->string('code')->nullable()->after('ticket_id');
            $table->date('due_date')->nullable()->after('priority');
            $table->timestamp('opened_at')->nullable()->after('due_date');
            $table->timestamp('closed_at')->nullable()->after('opened_at');
        });

        Schema::table('chatbot_marketing_campaigns', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chatbot_marketing_posts', function (Blueprint $table) {
            $table->dropColumn(['content_type', 'publishing_platforms', 'published_at']);
        });

        Schema::table('chatbot_tickets', function (Blueprint $table) {
            $table->dropColumn(['type', 'code', 'due_date', 'opened_at', 'closed_at']);
        });

        Schema::table('chatbot_marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
