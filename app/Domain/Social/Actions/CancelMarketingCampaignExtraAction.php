<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignExtra;
use Exception;

class CancelMarketingCampaignExtraAction
{
    /**
     * Delete a marketing campaign extra if it hasn't been invoiced.
     */
    public function execute(MarketingCampaignExtra $extra): void
    {
        if ($extra->invoice_id) {
            throw new Exception("Impossibile eliminare l'extra: è già stato associato a una fattura.");
        }

        $extra->update([
            'status' => \App\Enums\Social\MarketingCampaignExtraStatus::Cancelled,
        ]);
    }
}
