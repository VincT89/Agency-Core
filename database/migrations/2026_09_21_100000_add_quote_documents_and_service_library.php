<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('document_reference', 80)->nullable();
            $table->date('document_date')->nullable();
            $table->text('introduction')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('ai_instructions')->nullable();
            $table->string('price_note', 150)->nullable();
            $table->json('issuer_snapshot')->nullable();
        });
        Schema::table('quote_items', function (Blueprint $table) {
            $table->text('summary')->nullable();
            $table->string('delivery_summary')->nullable();
            $table->string('delivery_terms', 500)->nullable();
        });
        Schema::create('quote_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('delivery_summary')->nullable();
            $table->string('delivery_terms', 500)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('fingerprint', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_services');
        Schema::table('quote_items', fn (Blueprint $table) => $table->dropColumn(['summary', 'delivery_summary', 'delivery_terms']));
        Schema::table('quotes', fn (Blueprint $table) => $table->dropColumn(['document_reference', 'document_date', 'introduction', 'payment_terms', 'ai_instructions', 'price_note', 'issuer_snapshot']));
    }
};
