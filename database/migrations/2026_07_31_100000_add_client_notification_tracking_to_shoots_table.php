<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            $table->timestamp('client_notified_at')
                ->nullable()
                ->after('client_confirmation_channel');
            $table->string('client_notification_recipient')
                ->nullable()
                ->after('client_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            $table->dropColumn([
                'client_notified_at',
                'client_notification_recipient',
            ]);
        });
    }
};
