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
        Schema::create('editorial_plan_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_id')->nullable()->constrained('social_posts')->nullOnDelete();
            
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->json('platforms')->nullable();
            $table->string('topic')->nullable();
            $table->string('status')->default('empty');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editorial_plan_slots');
    }
};
