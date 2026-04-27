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
        Schema::create('editorial_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            
            $table->datetime('scheduled_at');
            $table->string('platform');
            $table->string('status')->default('scheduled');
            
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->constrained('users');
            
            $table->datetime('published_at')->nullable();
            $table->datetime('cancelled_at')->nullable();
            
            $table->timestamps();
            
            // Indexes per performance
            $table->index(['social_post_id', 'status']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editorial_slots');
    }
};
