<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shoot_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shoot_id')->constrained('shoots')->cascadeOnDelete();
            
            $table->date('date');
            $table->string('period'); // morning, afternoon
            $table->time('starts_at');
            $table->time('ends_at');
            
            $table->string('status')->default('proposed'); // proposed, accepted, rejected
            $table->timestamp('responded_at')->nullable();
            $table->text('photographer_note')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Add foreign key constraint to shoots.selected_slot_id now that shoot_slots exists
        Schema::table('shoots', function (Blueprint $table) {
            $table->foreign('selected_slot_id')->references('id')->on('shoot_slots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            $table->dropForeign(['selected_slot_id']);
        });
        Schema::dropIfExists('shoot_slots');
    }
};
