<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = App\Models\User::factory()->create(['role' => App\Enums\UserRole::Photographer]);
echo "Testing Client...\n";
try {
    App\Models\Client::whereHas('projects.users', function($q) use ($u) {
        $q->where('users.id', $u->id);
    })->count();
    echo "Success!\n";
} catch (\Throwable $e) {
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
