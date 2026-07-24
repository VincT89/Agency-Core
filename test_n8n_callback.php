<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$posts = App\Models\MarketingCampaignPost::latest('id')->take(5)->get();
foreach($posts as $p) {
    echo "ID: {$p->id}, Request ID: {$p->n8n_request_id}\n";
}
