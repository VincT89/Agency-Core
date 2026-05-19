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
        Schema::create('social_connection_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('token', 80)->unique();
            $table->json('platforms');

            $table->string('status')->default('pending');
            // pending, completed, expired, cancelled

            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamp('last_sent_at')->nullable();
            $table->json('sody_response')->nullable();
            $table->text('sody_error')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_connection_requests');
    }
};
