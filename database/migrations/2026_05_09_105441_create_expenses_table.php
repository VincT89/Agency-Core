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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->nullableMorphs('expenseable');

            $table->string('title');
            $table->text('description')->nullable();

            $table->decimal('amount', 10, 2);

            $table->string('category')->nullable();
            $table->string('supplier')->nullable();

            $table->date('expense_date');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('status')->default('pending');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'due_date']);
            $table->index(['user_id', 'expense_date']);
            $table->index(['user_id', 'expenseable_type', 'expenseable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
