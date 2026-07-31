<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->string('default_payment_method', 4)
                ->default('MP05')
                ->after('iban');
        });

        Schema::create('electronic_invoice_transmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('provider', 32)->default('aruba');
            $table->string('environment', 16);
            $table->string('mode', 16);
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 32)->index();
            $table->string('xml_filename');
            $table->char('xml_hash', 64);
            $table->longText('xml_content');
            $table->string('request_identifier', 100)->nullable();
            $table->string('upload_filename')->nullable();
            $table->string('provider_invoice_id', 100)->nullable();
            $table->string('sdi_id', 100)->nullable();
            $table->string('provider_status', 100)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->json('response_payload')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('last_status_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['invoice_id', 'attempt_number'],
                'electronic_invoice_attempt_unique'
            );
            $table->index(
                ['invoice_id', 'mode', 'status'],
                'electronic_invoice_lookup_index'
            );
            $table->index(
                ['provider', 'environment', 'upload_filename'],
                'electronic_invoice_provider_file_index'
            );
            $table->index('sdi_id');
        });

        Schema::create('electronic_invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_invoice_transmission_id');
            $table->foreign(
                'electronic_invoice_transmission_id',
                'electronic_invoice_events_transmission_fk'
            )->references('id')
                ->on('electronic_invoice_transmissions')
                ->cascadeOnDelete();
            $table->char('event_key', 64)->unique();
            $table->string('source', 16);
            $table->string('type', 32);
            $table->string('status', 100)->nullable();
            $table->string('provider_filename')->nullable();
            $table->string('sdi_id', 100)->nullable();
            $table->json('payload')->nullable();
            $table->longText('document_content')->nullable();
            $table->char('document_hash', 64)->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(
                ['electronic_invoice_transmission_id', 'occurred_at'],
                'electronic_invoice_event_timeline_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_invoice_events');
        Schema::dropIfExists('electronic_invoice_transmissions');

        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->dropColumn('default_payment_method');
        });
    }
};
