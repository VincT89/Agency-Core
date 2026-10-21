<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->restrictOnDelete();
            $table->string('name');
            $table->char('identity_key', 64)->unique();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
        foreach (['expenses', 'expense_recurrences'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->constrained('expense_categories')->restrictOnDelete();
                $table->foreignId('subcategory_id')->nullable()->constrained('expense_categories')->restrictOnDelete();
            });
            /** Existing labels become reusable categories without changing amounts, dates or document links. */
            DB::table($name)->whereNotNull('category')->select('id', 'category')->orderBy('id')->chunkById(500, function ($rows) use ($name) {
                foreach ($rows as $row) {
                    $label = trim(preg_replace('/\s+/u', ' ', $row->category));
                    if ($label === '') {
                        continue;
                    }
                    $key = hash('sha256', '0:'.mb_strtolower($label));
                    DB::table('expense_categories')->insertOrIgnore([
                        'name' => $label, 'identity_key' => $key, 'active' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table($name)->where('id', $row->id)->update([
                        'category_id' => DB::table('expense_categories')->where('identity_key', $key)->value('id'),
                    ]);
                }
            });
        }
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('hosting_service_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('invoice_imported_service_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_index');
            $table->foreignId('hosting_service_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['invoice_id', 'line_index'], 'imported_service_line_unique');
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['category_id', 'subcategory_id', 'expense_date'], 'expenses_category_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_imported_service_links');
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('hosting_service_id'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropIndex('expenses_category_date_index'));
        foreach (['expenses', 'expense_recurrences'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('subcategory_id');
                $table->dropConstrainedForeignId('category_id');
            });
        }
        Schema::dropIfExists('expense_categories');
    }
};
