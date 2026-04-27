<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
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

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('code')->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('type')->default('support');
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');

            $table->timestamp('opened_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->text('resolution_notes')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('client_id');
            $table->index('project_id');
            $table->index('created_by');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('priority');
            $table->index('type');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};