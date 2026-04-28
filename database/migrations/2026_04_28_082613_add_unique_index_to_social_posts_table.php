<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SocialPost;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Verifica duplicati
        $duplicates = SocialPost::whereNotNull('editorial_plan_slot_id')
            ->select('editorial_plan_slot_id')
            ->groupBy('editorial_plan_slot_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->count() > 0) {
            $slotIds = $duplicates->pluck('editorial_plan_slot_id')->implode(', ');
            throw new \Exception("Impossibile applicare unique index. Trovati duplicati su editorial_plan_slot_id: {$slotIds}");
        }

        Schema::table('social_posts', function (Blueprint $table) {
            $table->unique('editorial_plan_slot_id', 'social_posts_editorial_plan_slot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropUnique('social_posts_editorial_plan_slot_unique');
        });
    }
};
