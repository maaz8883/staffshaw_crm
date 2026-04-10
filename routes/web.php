<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\TargetController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// Admin Authentication Routes
Route::prefix('admin')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

    // Protected Admin Routes
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('users/datatable', [UserController::class, 'datatable'])->name('admin.users.datatable');
        Route::resource('users', UserController::class)
            ->names([
                'index' => 'admin.users.index',
                'create' => 'admin.users.create',
                'store' => 'admin.users.store',
                'show' => 'admin.users.show',
                'edit' => 'admin.users.edit',
                'update' => 'admin.users.update',
                'destroy' => 'admin.users.destroy',
            ]);

        Route::get('teams/datatable', [TeamController::class, 'datatable'])->name('admin.teams.datatable');
        Route::resource('teams', TeamController::class)
            ->names([
                'index' => 'admin.teams.index',
                'create' => 'admin.teams.create',
                'store' => 'admin.teams.store',
                'show' => 'admin.teams.show',
                'edit' => 'admin.teams.edit',
                'update' => 'admin.teams.update',
                'destroy' => 'admin.teams.destroy',
            ]);

        Route::get('companies/datatable', [CompanyController::class, 'datatable'])->name('admin.companies.datatable');
        Route::get('companies/{company}/teams', [CompanyController::class, 'teams'])->name('admin.companies.teams');
        Route::resource('companies', CompanyController::class)
            ->names([
                'index' => 'admin.companies.index',
                'create' => 'admin.companies.create',
                'store' => 'admin.companies.store',
                'edit' => 'admin.companies.edit',
                'update' => 'admin.companies.update',
                'destroy' => 'admin.companies.destroy',
            ])
            ->except(['show']);

        Route::get('notifications', [NotificationController::class, 'index'])->name('admin.notifications.index');
        Route::get('notifications/poll', [NotificationController::class, 'poll'])->name('admin.notifications.poll');
        Route::get('notifications/{id}/read', [NotificationController::class, 'follow'])->name('admin.notifications.follow');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('admin.notifications.read-all');

        // Profile Routes
        Route::get('/profile', [ProfileController::class, 'show'])->name('admin.profile.show');
        Route::put('/profile', [ProfileController::class, 'updateProfile'])->name('admin.profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');

        // Target Routes
        Route::get('targets', [TargetController::class, 'index'])->name('admin.targets.index');
        Route::post('targets/team/{team}', [TargetController::class, 'setTeamTarget'])->name('admin.targets.team');
        Route::post('targets/user/{team}', [TargetController::class, 'setUserTarget'])->name('admin.targets.user');

        Route::get('reports', [ReportController::class, 'index'])->name('admin.reports.index');
        Route::get('reports/company', [ReportController::class, 'company'])->name('admin.reports.company');
        Route::get('reports/team', [ReportController::class, 'team'])->name('admin.reports.team');
        Route::get('reports/sales', [ReportController::class, 'sales'])->name('admin.reports.sales');
        Route::get('reports/user', [ReportController::class, 'user'])->name('admin.reports.user');

        // Sales Routes
        Route::get('sales/datatable', [SaleController::class, 'datatable'])->name('admin.sales.datatable');
        Route::post('sales/{sale}/approve', [SaleController::class, 'approve'])->name('admin.sales.approve');
        Route::post('sales/{sale}/reject', [SaleController::class, 'reject'])->name('admin.sales.reject');
        Route::resource('sales', SaleController::class)
            ->names([
                'index'   => 'admin.sales.index',
                'create'  => 'admin.sales.create',
                'store'   => 'admin.sales.store',
                'show'    => 'admin.sales.show',
                'edit'    => 'admin.sales.edit',
                'update'  => 'admin.sales.update',
                'destroy' => 'admin.sales.destroy',
            ]);
    });
});
