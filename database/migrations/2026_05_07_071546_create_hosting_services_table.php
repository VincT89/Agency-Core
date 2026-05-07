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
        Schema::create('hosting_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->index(); // domain, hosting, website, maintenance, email, dns, other
            $table->string('name');
            $table->string('domain')->nullable()->index();
            $table->string('provider')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 50)->default('active')->index();
            $table->string('access_url')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->date('renewal_date')->nullable()->index();
            $table->decimal('renewal_cost', 10, 2)->nullable();
            $table->decimal('resource_cost', 10, 2)->nullable();
            $table->string('billing_cycle', 50)->nullable(); // monthly, yearly
            $table->text('notes')->nullable();
            $table->timestamp('last_intervention_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hosting_services');
    }
};
