<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(
                ['user_id', 'date', 'starts_at', 'ends_at'],
                'user_availabilities_exact_slot_unique'
            );
            $table->index(['date', 'user_id'], 'user_availabilities_date_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_availabilities');
    }
};
