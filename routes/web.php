<?php

use App\Http\Controllers\Admin\Social\AgencyMetaOAuthController;
use App\Http\Controllers\Admin\Social\TikTokOAuthController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\FirstAccessController;
use App\Http\Controllers\BillingProfileController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EconomicSummaryController;
use App\Http\Controllers\HostingServiceController;
use App\Http\Controllers\HostingServiceInterventionController;
use App\Http\Controllers\HostingServicePasswordController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceFiscalController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\NextcloudDownloadController;
use App\Http\Controllers\NextcloudPreviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ShootRedirectController;
use App\Http\Controllers\Social\SocialPublicationMediaDeliveryController;
use App\Http\Controllers\SocialMedia\TemporaryVideoPreviewController;
use App\Http\Controllers\SocialMediaDeliveryController;
use App\Http\Controllers\TaskChecklistItemController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TicketChecklistItemController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Livewire\Admin\Shooting\ShootShow;
use App\Livewire\Admin\Shooting\ShootsIndex;
use App\Livewire\Admin\Social\AgencySocialConnections;
use App\Livewire\Admin\Social\SocialOperationsDashboard;
use App\Livewire\Dashboard\UserDailyNotes;
use App\Livewire\Expenses\ExpenseForm;
use App\Livewire\Expenses\ExpenseShow;
use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\Photography\Shooting\MyShootShow;
use App\Livewire\Photography\Shooting\MyShootsIndex;
use App\Livewire\Public\MarketingCampaignPostReview;
use App\Livewire\Social\MarketingCampaignCalendar;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignCreate;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostCreate;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignShow;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignsIndex;
use App\Livewire\Social\Shooting\CreateRequest;
use App\Livewire\Social\Shooting\RequestShow;
use App\Livewire\Social\Shooting\RequestsIndex;
use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::view('/privacy-policy', 'legal.privacy-policy')
    ->name('legal.privacy-policy');

Route::view('/terms-of-service', 'legal.terms-of-service')
    ->name('legal.terms-of-service');

// Route pubbliche per Clienti
Route::get('/client/marketing-campaign-posts/{token}', MarketingCampaignPostReview::class)
    ->name('public.marketing-campaign-posts.review')
    ->middleware('throttle:30,1');

Route::get('/publication/{publication}/media/{mediaIndex}/deliver', [SocialPublicationMediaDeliveryController::class, 'deliver'])
    ->name('public.social.publication-media.deliver')->middleware('throttle:social-media-delivery');

Route::get('/media/marketing-campaign-posts/{path}', function (string $path) {
    abort_if(str_contains($path, '..') || str_contains($path, '\\'), 404);

    $fullPath = str_starts_with($path, 'marketing/campaign-posts/')
        ? $path
        : 'marketing/campaign-posts/'.$path;
    abort_unless(Storage::disk('public')->exists($fullPath), 404);

    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    abort_unless(in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov', 'webm', 'm4v']), 404);

    $mime = Storage::disk('public')->mimeType($fullPath);
    abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm', 'video/quicktime']), 404);

    return Storage::disk('public')->response($fullPath);
})->where('path', '.*')
    ->middleware(['signed', 'throttle:social-media-delivery'])
    ->name('media.marketing-campaign-posts');

Route::get('/media/{path}', function (string $path) {
    abort_if(str_contains($path, '..') || str_contains($path, '\\'), 404);
    abort_unless(str_starts_with($path, 'clients/logos/'), 404);
    abort_unless(Storage::disk('public')->exists($path), 404);

    $mime = Storage::disk('public')->mimeType($path);
    abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp']), 404);

    return Storage::disk('public')->response($path);
})->where('path', '.*')
    ->middleware(['signed', 'throttle:social-media-delivery'])
    ->name('media.public');

Route::get('/nextcloud/preview', NextcloudPreviewController::class)
    ->middleware(['auth', 'throttle:nextcloud-preview'])
    ->name('nextcloud.preview');

Route::get('/nextcloud/download', NextcloudDownloadController::class)
    ->middleware(['auth', 'throttle:nextcloud-preview'])
    ->name('nextcloud.download');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'force.password.change'])->name('dashboard');

// Route di setup iniziale protette solo da auth
Route::middleware('auth')->group(function () {
    Route::get('/password/setup', [FirstAccessController::class, 'show'])
        ->name('password.setup');
    Route::post('/password/setup', [FirstAccessController::class, 'update'])
        ->name('password.setup.update');
});

Route::middleware(['auth', 'force.password.change'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('clients', ClientController::class);
    Route::resource('projects', ProjectController::class);
    Route::resource('tickets', TicketController::class);
    Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])
        ->name('tickets.update-status');
    Route::post('tickets/{ticket}/checklist-items', [TicketChecklistItemController::class, 'store'])
        ->name('tickets.checklist-items.store');
    Route::patch('ticket-checklist-items/{item}', [TicketChecklistItemController::class, 'update'])
        ->name('ticket-checklist-items.update');
    Route::patch('ticket-checklist-items/{item}/toggle', [TicketChecklistItemController::class, 'toggle'])
        ->name('ticket-checklist-items.toggle');
    Route::delete('ticket-checklist-items/{item}', [TicketChecklistItemController::class, 'destroy'])
        ->name('ticket-checklist-items.destroy');

    // Task
    Route::resource('tasks', TaskController::class);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])
        ->name('tasks.update-status');

    Route::post('tasks/{task}/checklist-items', [TaskChecklistItemController::class, 'store'])
        ->name('tasks.checklist-items.store');

    Route::patch('task-checklist-items/{item}', [TaskChecklistItemController::class, 'update'])
        ->name('task-checklist-items.update');

    Route::patch('task-checklist-items/{item}/toggle', [TaskChecklistItemController::class, 'toggle'])
        ->name('task-checklist-items.toggle');

    Route::delete('task-checklist-items/{item}', [TaskChecklistItemController::class, 'destroy'])
        ->name('task-checklist-items.destroy');

    // Team
    Route::resource('teams', TeamController::class);

    // Shooting (Redirect Legacy)
    Route::get('/shoots', [ShootRedirectController::class, 'index'])->name('shoots.index');
    Route::get('/shoots/{shoot}', [ShootRedirectController::class, 'show'])->name('shoots.show');

    // SOCIAL (Marketing)
    Route::get('social/calendar', MarketingCampaignCalendar::class)->name('social.calendar');

    Route::prefix('social/campaigns')->name('marketing-campaigns.')->group(function () {
        Route::get('/', MarketingCampaignsIndex::class)->name('index');
        Route::get('/create', MarketingCampaignCreate::class)->name('create');
        Route::get('/{campaign}', MarketingCampaignShow::class)->name('show');
        Route::get('/{campaign}/posts/create', MarketingCampaignPostCreate::class)->name('posts.create');
        Route::get('/{campaign}/posts/{post}', MarketingCampaignPostShow::class)->name('posts.show');
    });

    Route::prefix('social/shooting')->name('social.shooting.')->group(function () {
        Route::get('/', RequestsIndex::class)->name('index');
        Route::get('/create', CreateRequest::class)->name('create');
        Route::get('/{shoot}', RequestShow::class)->name('show');
    });

    // FOTOGRAFIA
    Route::prefix('fotografia/shooting')->name('photography.shooting.')->group(function () {
        Route::get('/', MyShootsIndex::class)->name('index');
        Route::get('/{shoot}', MyShootShow::class)->name('show');
    });

    // ADMIN
    Route::prefix('admin/shooting')->name('admin.shooting.')->group(function () {
        Route::get('/', ShootsIndex::class)->name('index');
        Route::get('/{shoot}', ShootShow::class)->name('show');
    });

    // ADMIN - SOCIAL CONNECTIONS
    Route::prefix('admin/social/connections')->name('admin.social.connections.')->middleware('can:manage_social_connections')->group(function () {
        // Il Livewire Index verrà aggiunto qui successivamente
        Route::get('/', AgencySocialConnections::class)->name('index');

        Route::get('/meta/redirect', [AgencyMetaOAuthController::class, 'redirect'])->name('meta.redirect');
        Route::get('/meta/callback', [AgencyMetaOAuthController::class, 'callback'])->name('meta.callback');
    });

    // ADMIN - TIKTOK CONNECTIONS (Client Specific)
    Route::prefix('admin/social/tiktok')->name('admin.social.tiktok.')->middleware('can:manage_social_connections')->group(function () {
        Route::get('/redirect', [TikTokOAuthController::class, 'redirect'])->name('redirect');
        Route::get('/callback', [TikTokOAuthController::class, 'callback'])->name('callback');
    });

    // ADMIN - SOCIAL OPERATIONS
    Route::prefix('admin/social/operations')->name('admin.social.operations.')->middleware('can:manage_social_operations')->group(function () {
        Route::get('/', SocialOperationsDashboard::class)->name('index');
    });
    // AMMINISTRAZIONE - SPESE
    Route::get('/expenses', ExpensesIndex::class)->name('expenses.index');
    Route::get('/expenses/create', ExpenseForm::class)->name('expenses.create');
    Route::get('/expenses/{expense}', ExpenseShow::class)->name('expenses.show');
    Route::get('/expenses/{expense}/edit', ExpenseForm::class)->name('expenses.edit');

    Route::resource('calendar-events', CalendarEventController::class);
    Route::patch('calendar-events/{calendar_event}/date', [CalendarEventController::class, 'updateDate'])->name('calendar-events.update-date');
    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/fiscal/prepare', [InvoiceFiscalController::class, 'prepare'])
        ->name('invoices.fiscal.prepare');
    Route::post('invoices/{invoice}/fiscal/reopen', [InvoiceFiscalController::class, 'reopen'])
        ->name('invoices.fiscal.reopen');
    Route::post('invoices/{invoice}/fiscal/validate', [InvoiceFiscalController::class, 'validateWithAruba'])
        ->name('invoices.fiscal.validate');
    Route::post('invoices/{invoice}/fiscal/send', [InvoiceFiscalController::class, 'send'])
        ->name('invoices.fiscal.send');
    Route::post('invoices/{invoice}/fiscal/sync', [InvoiceFiscalController::class, 'sync'])
        ->name('invoices.fiscal.sync');
    Route::get('invoices/{invoice}/fiscal/xml', [InvoiceFiscalController::class, 'downloadXml'])
        ->name('invoices.fiscal.xml');
    Route::post('invoices/{invoice}/items', [InvoiceItemController::class, 'store'])->name('invoices.items.store');
    Route::delete('invoices/{invoice}/items/{item}', [InvoiceItemController::class, 'destroy'])->name('invoices.items.destroy');
    Route::get('/billing-profile', [BillingProfileController::class, 'edit'])
        ->name('billing-profile.edit');
    Route::put('/billing-profile', [BillingProfileController::class, 'update'])
        ->name('billing-profile.update');
    Route::post('/billing-profile/aruba/test', [BillingProfileController::class, 'testArubaConnection'])
        ->name('billing-profile.aruba.test');
    Route::resource('payments', PaymentController::class);
    Route::get('/economic-summary', EconomicSummaryController::class)->name('economic-summary.index');

    Route::post('/attachments', [AttachmentController::class, 'store'])
        ->name('attachments.store');

    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('attachments.download');

    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('attachments.destroy');

    // Hosting e Manutenzioni
    Route::get('hosting-services/{hosting_service}/password', [HostingServicePasswordController::class, 'show'])
        ->name('hosting-services.password.show');
    Route::resource('hosting-services', HostingServiceController::class);
    Route::post('hosting-services/{hosting_service}/interventions', [HostingServiceInterventionController::class, 'store'])
        ->name('hosting-services.interventions.store');
    Route::delete('hosting-services/{hosting_service}/interventions/{intervention}', [HostingServiceInterventionController::class, 'destroy'])
        ->name('hosting-services.interventions.destroy');

    Route::resource('users', UserController::class)->except(['show']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->name('users.reset-password');
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
        ->name('users.toggle-status');

    // Audit logs (solo admin)
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:system.admin')
        ->name('audit-logs.index');

    // Notifiche
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsReadAndRedirect'])
        ->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    // API interna: progetti per cliente (usata dai form JS)
    Route::get('/api/clients/{client}/projects', function (Client $client) {
        Gate::authorize('view', $client);

        return response()->json(
            $client->projects()->where('status', 'active')->get(['id', 'name'])
        );
    })->name('api.client.projects');

    // API interna: ricerca clienti e quick-store
    Route::get('/api/clients/search', [ClientController::class, 'search'])->name('api.clients.search');
    Route::post('/api/clients/quick-store', [ClientController::class, 'quickStore'])->name('api.clients.quick-store');

    // Note Operative
    Route::get('/daily-notes', UserDailyNotes::class)->name('daily-notes.index');
});

// Route pubblica protetta da firma e throttling (usata per erogazione asincrona ai provider Social)
Route::get('/social/media/{media}', SocialMediaDeliveryController::class)
    ->name('social.media.delivery')
    ->middleware(['signed', 'throttle:social-media-delivery']);

// Route per la preview dei video temporanei di Livewire con supporto HTTP 206 Partial Content
Route::get('/social/temporary-video-preview/{filename}', TemporaryVideoPreviewController::class)
    ->name('social.temporary-video-preview')
    ->middleware(['web', 'signed', 'auth']);

require __DIR__.'/auth.php';
