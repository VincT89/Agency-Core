<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = App\Models\MarketingCampaignPost::find(33);
$p->status = \App\Enums\Social\MarketingCampaignPostStatus::ClientApproved;
$p->save();
$p->publications()->delete();
echo "Status reverted to: " . $p->status->value . " and publications deleted.\n";
