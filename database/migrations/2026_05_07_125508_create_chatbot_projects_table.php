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
        Schema::create('chatbot_projects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chatbot_client_id')
                ->constrained('chatbot_clients')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('project_id')->unique();

            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status', 50)->nullable();
            $table->text('description')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('synced_at')->useCurrent()->index();

            $table->timestamps();

            $table->index(['chatbot_client_id', 'status']);
            $table->index(['chatbot_client_id', 'source_updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_projects');
    }
};
