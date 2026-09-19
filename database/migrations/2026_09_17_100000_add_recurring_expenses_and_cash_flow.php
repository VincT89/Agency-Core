<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->string('category')->nullable();
            $table->string('supplier')->nullable();
            $table->string('document_kind')->default('invoice');
            $table->string('frequency');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('expense_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('issuer');
            $table->string('issuer_identifier', 50)->nullable();
            $table->char('issuer_country', 2)->nullable();
            $table->string('number', 100);
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('fingerprint', 64)->unique();
            $table->string('aruba_environment', 20)->nullable();
            $table->string('aruba_account', 64)->nullable();
            $table->string('aruba_id', 100)->nullable();
            $table->unsignedInteger('aruba_body_index')->nullable();
            $table->timestamps();
            $table->unique(['aruba_account', 'aruba_id', 'aruba_body_index'], 'expense_document_aruba_unique');
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_recurrence_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('recurrence_date')->nullable();
            $table->boolean('recurrence_overridden')->default(false);
            $table->foreignId('expense_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document_kind')->default('invoice');
            $table->unique(['expense_recurrence_id', 'recurrence_date'], 'expense_recurrence_date_unique');
            $table->index(['status', 'due_date']);
        });
        Schema::create('manual_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('payer')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('expected_on');
            $table->date('received_on')->nullable();
            $table->string('status')->default('expected');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'expected_on']);
        });
        Schema::create('cash_flow_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->decimal('opening_balance', 14, 2);
            $table->date('balance_date');
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_settings');
        Schema::dropIfExists('manual_incomes');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expense_recurrence_date_unique');
            $table->dropIndex(['status', 'due_date']);
            $table->dropConstrainedForeignId('expense_recurrence_id');
            $table->dropConstrainedForeignId('expense_document_id');
            $table->dropColumn(['recurrence_date', 'recurrence_overridden', 'document_kind']);
        });
        Schema::dropIfExists('expense_documents');
        Schema::dropIfExists('expense_recurrences');
    }
};
