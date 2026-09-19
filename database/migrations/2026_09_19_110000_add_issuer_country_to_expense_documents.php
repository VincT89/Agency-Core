<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Earlier installations already ran the initial expense migration without this column.
        if (! Schema::hasColumn('expense_documents', 'issuer_country')) {
            Schema::table('expense_documents', function (Blueprint $table) {
                $table->char('issuer_country', 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Also part of the initial table definition: retain stored countries on rollback.
    }
};
