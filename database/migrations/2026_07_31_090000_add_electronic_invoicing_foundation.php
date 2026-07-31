<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('profile_key')->default('default')->unique();
            $table->string('legal_name');
            $table->char('vat_country_code', 2);
            $table->string('vat_number', 28);
            $table->string('tax_code', 28)->nullable();
            $table->string('fiscal_regime', 4);
            $table->string('address');
            $table->string('postal_code', 12);
            $table->string('city', 100);
            $table->string('province', 5)->nullable();
            $table->char('country_code', 2);
            $table->string('email')->nullable();
            $table->string('pec')->nullable();
            $table->string('recipient_code', 7)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('invoice_series', 20)->default('FE');
            $table->unsignedInteger('initial_sequence')->default(1);
            $table->timestamps();
        });

        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_profile_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('series', 20);
            $table->unsignedInteger('next_number');
            $table->timestamps();

            $table->unique(
                ['billing_profile_id', 'year', 'series'],
                'invoice_number_sequences_scope_unique'
            );
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('unit_of_measure', 10)->default('NR');
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->string('vat_nature', 10)->nullable();
            $table->string('vat_reference')->nullable();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_with_tax', 12, 2)->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('fiscal_status')->default('not_prepared')->index();
            $table->string('fiscal_document_type', 4)->default('TD01');
            $table->string('fiscal_number')->nullable()->unique();
            $table->unsignedInteger('fiscal_sequence_number')->nullable();
            $table->timestamp('fiscal_locked_at')->nullable();
            $table->json('fiscal_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_status',
                'fiscal_document_type',
                'fiscal_number',
                'fiscal_sequence_number',
                'fiscal_locked_at',
                'fiscal_snapshot',
            ]);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'unit_of_measure',
                'vat_rate',
                'vat_nature',
                'vat_reference',
                'tax_amount',
                'total_with_tax',
            ]);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });

        Schema::dropIfExists('invoice_number_sequences');
        Schema::dropIfExists('billing_profiles');
    }
};
