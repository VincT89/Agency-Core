<?php

namespace App\Enums\Social;

enum MarketingProjectType: string
{
    case OneShot = 'one_shot';
    case EditorialPlan = 'editorial_plan';

    public function label(): string
    {
        return match($this) {
            self::OneShot => 'Una Tantum',
            self::EditorialPlan => 'Piano Editoriale',
        };
    }
}
