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
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('title');
            $table->string('status')->index();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->string('format')->default('1080x1350');
            $table->string('source')->default('n8n');
            $table->timestamp('sent_to_client_at')->nullable();
            $table->timestamp('client_approved_at')->nullable();
            $table->timestamp('client_rejected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
