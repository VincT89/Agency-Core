<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('project_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('number')->unique();
            $table->date('issue_date');
            $table->date('due_date')->nullable();

            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('EUR');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('client_id');
            $table->index('project_id');
            $table->index('created_by');
            $table->index('status');
            $table->index('issue_date');
            $table->index('due_date');
            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};