<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Enums\Social\PublicationStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Mark legacy publications that are stuck in 'pending' or 'publishing' without a valid snapshot as 'abandoned'
        DB::table('marketing_campaign_post_publications')
            ->whereIn('status', [PublicationStatus::Pending->value, PublicationStatus::Publishing->value])
            ->whereNull('snapshot_schema_version')
            ->update([
                'status' => 'abandoned',
                'error_message' => 'Legacy publication replaced by snapshot architecture.',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is destructive because we cannot distinguish between 
        // legacy records that were 'pending' vs 'publishing' originally.
        // Reverting would force all abandoned records to 'pending', which is lossy.
        throw new \BadMethodCallException('This migration is destructive and cannot be cleanly reversed. Legacy states are lost.');
    }
};
