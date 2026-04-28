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
        Schema::create('marketing_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('title');
            $table->text('brief')->nullable();
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('status')->default('draft');
            $table->json('platforms')->nullable();
            $table->string('publication_mode')->default('manual');
            
            $table->string('n8n_request_id')->nullable()->index();
            $table->datetime('submitted_to_n8n_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_projects');
    }
};
