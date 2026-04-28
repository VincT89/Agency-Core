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
        Schema::create('editorial_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_project_id')->constrained()->cascadeOnDelete();
            $table->integer('duration_days')->default(30);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('post_count')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editorial_plans');
    }
};
