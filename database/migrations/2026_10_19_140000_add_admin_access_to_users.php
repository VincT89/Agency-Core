<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('admin_access')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', 'developer')->where('admin_access', true)->where('status', 'active')->exists()
            && ! DB::table('users')->where('role', 'admin')->where('status', 'active')->exists()) {
            throw new RuntimeException('Ripristina un amministratore attivo con ruolo Admin prima di rimuovere admin_access.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_access');
        });
    }
};
