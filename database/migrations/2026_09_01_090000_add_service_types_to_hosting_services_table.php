<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_services', function (Blueprint $table) {
            $table->json('service_types')->nullable()->after('type');
        });

        DB::table('hosting_services')
            ->select(['id', 'type'])
            ->orderBy('id')
            ->chunkById(200, function ($services): void {
                foreach ($services as $service) {
                    DB::table('hosting_services')
                        ->where('id', $service->id)
                        ->update([
                            'service_types' => json_encode([$service->type], JSON_THROW_ON_ERROR),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('hosting_services', function (Blueprint $table) {
            $table->dropColumn('service_types');
        });
    }
};
