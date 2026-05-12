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
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->string('delivery_channel')->nullable()->after('source');
            $table->string('delivery_status')->nullable()->after('delivery_channel');
            $table->timestamp('delivery_requested_at')->nullable()->after('delivery_status');
            $table->timestamp('delivered_at')->nullable()->after('delivery_requested_at');
            $table->text('delivery_error')->nullable()->after('delivered_at');
            $table->string('external_message_id')->nullable()->after('delivery_error');
            $table->string('idempotency_key')->nullable()->unique()->after('external_message_id');
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->string('source')->nullable()->after('body');
            $table->string('delivery_channel')->nullable()->after('source');
            $table->string('delivery_status')->nullable()->after('delivery_channel');
            $table->timestamp('delivery_requested_at')->nullable()->after('delivery_status');
            $table->timestamp('delivered_at')->nullable()->after('delivery_requested_at');
            $table->text('delivery_error')->nullable()->after('delivered_at');
            $table->string('external_message_id')->nullable()->after('delivery_error');
            $table->string('idempotency_key')->nullable()->unique()->after('external_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_channel',
                'delivery_status',
                'delivery_requested_at',
                'delivered_at',
                'delivery_error',
                'external_message_id',
                'idempotency_key'
            ]);
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropColumn([
                'source',
                'delivery_channel',
                'delivery_status',
                'delivery_requested_at',
                'delivered_at',
                'delivery_error',
                'external_message_id',
                'idempotency_key'
            ]);
        });
    }
};
