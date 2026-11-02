<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_presence_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('availability_id')->nullable()->constrained('user_availabilities')->nullOnDelete();
            /** A nullable unique owner prevents two open sessions, including concurrent requests from different devices. */
            $table->unsignedBigInteger('active_user_id')->nullable()->unique();
            $table->string('connection_hash', 64);
            $table->date('date');
            /** UTC epoch seconds preserve elapsed time across timezone and daylight-saving changes. */
            $table->unsignedBigInteger('scheduled_start_at');
            $table->unsignedBigInteger('scheduled_end_at');
            $table->unsignedBigInteger('extension_deadline_at');
            $table->unsignedBigInteger('started_at');
            $table->unsignedBigInteger('last_seen_at');
            $table->unsignedBigInteger('extra_confirmed_at')->nullable();
            $table->unsignedBigInteger('ended_at')->nullable();
            $table->string('state', 24);
            $table->string('end_reason', 24)->nullable();
            $table->timestamps();
            $table->index(['date', 'user_id']);
            $table->index(['active_user_id', 'last_seen_at']);
        });

        Schema::create('user_presence_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presence_session_id')->constrained('user_presence_sessions')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->unsignedBigInteger('started_at');
            $table->unsignedBigInteger('ended_at')->nullable();
            $table->timestamps();
            $table->index(['presence_session_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_presence_intervals');
        Schema::dropIfExists('user_presence_sessions');
    }
};
