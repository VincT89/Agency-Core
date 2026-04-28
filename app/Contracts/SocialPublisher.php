<?php

namespace App\Contracts;

use App\Models\SocialPost;
use App\Models\ClientSocialAccount;
use App\DTO\Social\PublicationResult;
use Carbon\Carbon;

interface SocialPublisher
{
    public function publish(SocialPost $post, ClientSocialAccount $account): PublicationResult;
    
    public function schedule(SocialPost $post, ClientSocialAccount $account, Carbon $scheduledAt): PublicationResult;
}
