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
        Schema::dropIfExists('social_oauth_sessions');
        Schema::dropIfExists('social_connection_requests');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non implementato di proposito. La tabella è stata rimossa.
    }
};
