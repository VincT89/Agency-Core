<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TemporaryMediaUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CleanupTemporaryMedia extends Command
{
    protected $signature = 'social:cleanup-media';
    protected $description = 'Pulisce i media temporanei usati per il publishing social (Orphan Recovery)';

    public function handle()
    {
        $this->info("Pulizia media temporanei in corso...");

        // Eliminiamo i record più vecchi di 2 giorni 
        // per dare tempo a Meta di scaricarli e al sistema di fare retry
        $expiredMedia = TemporaryMediaUpload::where('created_at', '<', now()->subDays(2))
            ->where('cleanup_status', 'pending')
            ->get();

        $deletedCount = 0;

        foreach ($expiredMedia as $media) {
            try {
                if (Storage::disk('public')->exists($media->temp_path)) {
                    // Controlla se lo stesso file è usato da un altro upload più recente
                    // tramite hash deduplication
                    $isShared = TemporaryMediaUpload::where('hash', $media->hash)
                        ->where('id', '!=', $media->id)
                        ->where('created_at', '>=', now()->subDays(2))
                        ->exists();

                    if (!$isShared) {
                        Storage::disk('public')->delete($media->temp_path);
                    }
                }

                $media->update(['cleanup_status' => 'completed']);
                $deletedCount++;
            } catch (\Exception $e) {
                Log::error("Errore pulizia media", [
                    'media_id' => $media->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Puliti {$deletedCount} media temporanei (con orphan recovery).");
    }
}
