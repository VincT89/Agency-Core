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
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->string('source')->default('internal')->after('body');
        });
        
        Schema::table('marketing_campaign_post_comments', function (Blueprint $table) {
            $table->string('source')->default('internal')->after('visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('marketing_campaign_post_comments', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
