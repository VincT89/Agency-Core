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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Route pubbliche per Clienti
Route::get('/client/social-posts/{token}', \App\Livewire\Client\Social\SocialPostReview::class)
    ->name('client.social-posts.review');

Route::get('/review/{token}', \App\Livewire\Client\ReviewTokenHandler::class)
    ->name('client.review');

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
        
    // Team
    Route::resource('teams', \App\Http\Controllers\TeamController::class);

    // Shooting (Redirect Legacy)
    Route::get('/shoots', [\App\Http\Controllers\ShootRedirectController::class, 'index'])->name('shoots.index');
    Route::get('/shoots/{shoot}', [\App\Http\Controllers\ShootRedirectController::class, 'show'])->name('shoots.show');

    // SOCIAL (ex Marketing)
    Route::get('social/calendar', \App\Livewire\Social\EditorialCalendar::class)->name('social.calendar');

    Route::prefix('social/marketing-projects')->name('marketing-projects.')->group(function () {
        Route::get('/publication-board', \App\Livewire\Social\PublicationBoard::class)
            ->name('publication-board');
            
        Route::get('/', \App\Livewire\Social\MarketingProjects\MarketingProjectsIndex::class)->name('index');
        Route::get('/create', \App\Livewire\Social\MarketingProjects\MarketingProjectCreate::class)->name('create');
        Route::get('/{project}', \App\Livewire\Social\MarketingProjects\MarketingProjectShow::class)->name('show');
    });

    Route::prefix('social/shooting')->name('social.shooting.')->group(function () {
        Route::get('/', \App\Livewire\Social\Shooting\RequestsIndex::class)->name('index');
        Route::get('/create', \App\Livewire\Social\Shooting\CreateRequest::class)->name('create');
        Route::get('/{shoot}', \App\Livewire\Social\Shooting\RequestShow::class)->name('show');
    });

    Route::prefix('social/posts')->name('social.posts.')->group(function () {
        Route::get('/', \App\Livewire\Social\Posts\SocialPostsIndex::class)->name('index');
        Route::get('/{post}', \App\Livewire\Social\Posts\SocialPostShow::class)->name('show');
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
        return response()->json(
            $client->projects()->where('status', 'active')->get(['id', 'name'])
        );
    })->name('api.client.projects');

    // API interna: ricerca clienti e quick-store
    Route::get('/api/clients/search', [ClientController::class, 'search'])->name('api.clients.search');
    Route::post('/api/clients/quick-store', [ClientController::class, 'quickStore'])->name('api.clients.quick-store');
});

require __DIR__ . '/auth.php';
