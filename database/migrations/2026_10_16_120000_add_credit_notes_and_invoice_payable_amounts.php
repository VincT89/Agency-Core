<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('payable_total', 12, 2)->nullable();
            $table->decimal('credited_total', 12, 2)->default(0);
        });
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('number', 100);
            $table->date('issue_date');
            $table->string('fiscal_document_type', 4)->default('TD04');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->decimal('payable_total', 12, 2);
            $table->string('import_fingerprint', 64)->unique();
            $table->string('import_source', 20);
            $table->json('import_metadata');
            $table->timestamps();
            $table->index(['issue_date', 'number']);
        });
        Schema::create('credit_note_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['credit_note_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_allocations');
        Schema::dropIfExists('credit_notes');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['payable_total', 'credited_total']);
        });
    }
};
