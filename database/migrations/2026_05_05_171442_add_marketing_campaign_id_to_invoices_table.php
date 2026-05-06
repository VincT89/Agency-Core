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
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
            
            $table->foreignId('marketing_campaign_id')
                ->nullable()
                ->after('project_id')
                ->constrained('marketing_campaigns')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['marketing_campaign_id']);
            $table->dropColumn('marketing_campaign_id');
            // Nota: non possiamo revertire project_id a non-nullable se ci sono righe con project_id null
        });
    }
};
