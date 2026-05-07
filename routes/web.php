<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\EconomicSummaryController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskChecklistItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Route pubbliche per Clienti
Route::get('/client/marketing-campaign-posts/{token}', \App\Livewire\Public\MarketingCampaignPostReview::class)
    ->name('public.marketing-campaign-posts.review');

Route::get('/media/marketing-campaign-posts/{path}', function (string $path) {
    abort_if(str_contains($path, '..') || str_contains($path, '\\'), 404);
    
    $fullPath = str_starts_with($path, 'marketing/campaign-posts/') 
        ? $path 
        : 'marketing/campaign-posts/' . $path;
    abort_unless(\Illuminate\Support\Facades\Storage::disk('public')->exists($fullPath), 404);

    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    abort_unless(in_array($extension, ['jpg', 'jpeg', 'png', 'webp']), 404);

    $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($fullPath);
    abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp']), 404);

    return \Illuminate\Support\Facades\Storage::disk('public')->response($fullPath);
})->where('path', '.*')->name('media.marketing-campaign-posts');

Route::get('/media/{path}', function (string $path) {
    abort_if(str_contains($path, '..') || str_contains($path, '\\'), 404);
    abort_unless(str_starts_with($path, 'clients/logos/'), 404);
    abort_unless(\Illuminate\Support\Facades\Storage::disk('public')->exists($path), 404);

    $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($path);
    abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp']), 404);

    return \Illuminate\Support\Facades\Storage::disk('public')->response($path);
})->where('path', '.*')->name('media.public');

Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)
    ->middleware(['auth', 'force.password.change'])->name('dashboard');

// Route di setup iniziale protette solo da auth
Route::middleware('auth')->group(function () {
    Route::get('/password/setup', [\App\Http\Controllers\Auth\FirstAccessController::class, 'show'])
        ->name('password.setup');
    Route::post('/password/setup', [\App\Http\Controllers\Auth\FirstAccessController::class, 'update'])
        ->name('password.setup.update');
});

Route::middleware(['auth', 'force.password.change'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('clients', ClientController::class);
    Route::resource('projects', ProjectController::class);
    Route::resource('tickets', TicketController::class);
    
    // Task
    Route::resource('tasks', \App\Http\Controllers\TaskController::class);
    Route::patch('tasks/{task}/status', [\App\Http\Controllers\TaskController::class, 'updateStatus'])
        ->name('tasks.update-status');

    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])
        ->name('tasks.comments.store');

    Route::post('tasks/{task}/checklist-items', [TaskChecklistItemController::class, 'store'])
        ->name('tasks.checklist-items.store');

    Route::patch('task-checklist-items/{item}', [TaskChecklistItemController::class, 'update'])
        ->name('task-checklist-items.update');

    Route::patch('task-checklist-items/{item}/toggle', [TaskChecklistItemController::class, 'toggle'])
        ->name('task-checklist-items.toggle');

    Route::delete('task-checklist-items/{item}', [TaskChecklistItemController::class, 'destroy'])
        ->name('task-checklist-items.destroy');
        
    // Team
    Route::resource('teams', \App\Http\Controllers\TeamController::class);

    // Shooting (Redirect Legacy)
    Route::get('/shoots', [\App\Http\Controllers\ShootRedirectController::class, 'index'])->name('shoots.index');
    Route::get('/shoots/{shoot}', [\App\Http\Controllers\ShootRedirectController::class, 'show'])->name('shoots.show');

    // SOCIAL (Marketing)
    Route::get('social/calendar', \App\Livewire\Social\MarketingCampaignCalendar::class)->name('social.calendar');



    Route::prefix('social/campaigns')->name('marketing-campaigns.')->group(function () {
        Route::get('/', \App\Livewire\Social\MarketingCampaigns\MarketingCampaignsIndex::class)->name('index');
        Route::get('/create', \App\Livewire\Social\MarketingCampaigns\MarketingCampaignCreate::class)->name('create');
        Route::get('/{campaign}', \App\Livewire\Social\MarketingCampaigns\MarketingCampaignShow::class)->name('show');
    });

    Route::prefix('social/shooting')->name('social.shooting.')->group(function () {
        Route::get('/', \App\Livewire\Social\Shooting\RequestsIndex::class)->name('index');
        Route::get('/create', \App\Livewire\Social\Shooting\CreateRequest::class)->name('create');
        Route::get('/{shoot}', \App\Livewire\Social\Shooting\RequestShow::class)->name('show');
    });



    // FOTOGRAFIA
    Route::prefix('fotografia/shooting')->name('photography.shooting.')->group(function () {
        Route::get('/', \App\Livewire\Photography\Shooting\MyShootsIndex::class)->name('index');
        Route::get('/{shoot}', \App\Livewire\Photography\Shooting\MyShootShow::class)->name('show');
    });

    // ADMIN
    Route::prefix('admin/shooting')->name('admin.shooting.')->group(function () {
        Route::get('/', \App\Livewire\Admin\Shooting\ShootsIndex::class)->name('index');
        Route::get('/{shoot}', \App\Livewire\Admin\Shooting\ShootShow::class)->name('show');
    });
        
    Route::resource('calendar-events', CalendarEventController::class);
    Route::resource('invoices', InvoiceController::class);
    Route::resource('payments', PaymentController::class);
    Route::get('/economic-summary', EconomicSummaryController::class)->name('economic-summary.index');

    Route::post('/attachments', [AttachmentController::class, 'store'])
        ->name('attachments.store');

    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('attachments.download');

    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('attachments.destroy');

    // Hosting e Manutenzioni
    Route::get('hosting-services/{hosting_service}/password', [\App\Http\Controllers\HostingServicePasswordController::class, 'show'])
        ->name('hosting-services.password.show');
    Route::resource('hosting-services', \App\Http\Controllers\HostingServiceController::class);
    Route::post('hosting-services/{hosting_service}/interventions', [\App\Http\Controllers\HostingServiceInterventionController::class, 'store'])
        ->name('hosting-services.interventions.store');
    Route::delete('hosting-services/{hosting_service}/interventions/{intervention}', [\App\Http\Controllers\HostingServiceInterventionController::class, 'destroy'])
        ->name('hosting-services.interventions.destroy');

    Route::resource('users', \App\Http\Controllers\UserController::class)->except(['show']);
    Route::post('users/{user}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword'])
        ->name('users.reset-password');
    Route::post('users/{user}/toggle-status', [\App\Http\Controllers\UserController::class, 'toggleStatus'])
        ->name('users.toggle-status');

    // Audit logs (solo admin)
    Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');

    // Notifiche
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])
        ->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsReadAndRedirect'])
        ->name('notifications.read');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    // API interna: progetti per cliente (usata dai form JS)
    Route::get('/api/clients/{client}/projects', function (\App\Models\Client $client) {
        \Illuminate\Support\Facades\Gate::authorize('view', $client);
        return response()->json(
            $client->projects()->where('status', 'active')->get(['id', 'name'])
        );
    })->name('api.client.projects');

    // API interna: ricerca clienti e quick-store
    Route::get('/api/clients/search', [ClientController::class, 'search'])->name('api.clients.search');
    Route::post('/api/clients/quick-store', [ClientController::class, 'quickStore'])->name('api.clients.quick-store');
});

require __DIR__ . '/auth.php';
