<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPostMedia;
use App\Domain\Social\Services\HistoricalMediaProtectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DeleteMarketingCampaignPostMediaAction
{
    public function __construct(
        private readonly HistoricalMediaProtectionService $protectionService
    ) {}

    /**
     * @return array{status: 'scheduled'}
     */
    public function execute(MarketingCampaignPostMedia $media): array
    {
        $mediaId = $media->id;

        DB::transaction(function () use ($mediaId) {
            $lockedMedia = MarketingCampaignPostMedia::where('id', $mediaId)->lockForUpdate()->firstOrFail();
            
            // 2. Assert Deletable
            $this->protectionService->assertDeletable($lockedMedia);
            
            // 3. Acquire disk/path/source before deletion
            $info = [
                'disk' => $lockedMedia->disk,
                'path' => $lockedMedia->path,
                'source' => $lockedMedia->source,
            ];
            
            // 4. Delete DB record
            $lockedMedia->delete();
            
            // 5. Register afterCommit callback
            DB::afterCommit(function () use ($info) {
                $this->processPhysicalDeletion($info);
            });
        });
        
        return ['status' => 'scheduled'];
    }
    
    private function processPhysicalDeletion(array $info): void
    {
        // Local or n8n
        if (($info['source'] === 'local' || $info['source'] === 'n8n') && $info['disk'] === 'public' && !empty($info['path'])) {
            try {
                if (Storage::disk($info['disk'])->exists($info['path'])) {
                    $success = Storage::disk($info['disk'])->delete($info['path']);
                    if (!$success) {
                        Log::error("Failed to delete physical file (Storage returned false): {$info['path']} on disk {$info['disk']}");
                    }
                } else {
                    Log::warning("File not found during cleanup: {$info['path']} on disk {$info['disk']}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to delete physical file: " . $e->getMessage());
            }
        }
    }
}
