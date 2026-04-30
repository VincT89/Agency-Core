<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;

/**
 * @deprecated Usa CreateMarketingCampaignAction invece
 */
class CreateMarketingProjectAction
{
    public function __construct(private CreateMarketingCampaignAction $newAction)
    {}

    public function execute(array $data): MarketingProject
    {
        // Wrapper per retrocompatibilità
        $data['project_mode'] = 'existing';
        return $this->newAction->execute($data);
    }
}
