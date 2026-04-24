<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\OtpController;
use App\Http\Controllers\Admin\PendingRegistrationController;
use App\Http\Controllers\Admin\PpcController;
use App\Http\Controllers\Admin\SettingController;
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

    // OTP routes (guest)
    Route::middleware('guest')->group(function () {
        Route::post('otp/send', [OtpController::class, 'sendOtp'])->name('admin.otp.send');
        Route::post('otp/resend', [OtpController::class, 'resendOtp'])->name('admin.otp.resend');
        Route::get('otp/verify', [OtpController::class, 'showVerifyForm'])->name('admin.otp.verify.form');
        Route::post('otp/verify', [OtpController::class, 'verifyOtp'])->name('admin.otp.verify');
    });

    // Protected Admin Routes
    Route::middleware(['auth', 'active.account', 'single.session'])->group(function () {
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

        Route::get('pending-registrations', [PendingRegistrationController::class, 'index'])->name('admin.pending-registrations.index');
        Route::post('pending-registrations/{user}/approve', [PendingRegistrationController::class, 'approve'])->name('admin.pending-registrations.approve');
        Route::post('pending-registrations/{user}/reject', [PendingRegistrationController::class, 'reject'])->name('admin.pending-registrations.reject');

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

        // Settings (Admin only)
        Route::get('settings', [SettingController::class, 'index'])->name('admin.settings.index');
        Route::put('settings/otp', [SettingController::class, 'updateOtp'])->name('admin.settings.otp');
        Route::put('settings/smtp', [SettingController::class, 'updateSmtp'])->name('admin.settings.smtp');

        // Backup (Admin only)
        Route::get('backup', [BackupController::class, 'index'])->name('admin.backup.index');
        Route::post('backup', [BackupController::class, 'store'])->name('admin.backup.store');
        Route::get('backup/{file}/download', [BackupController::class, 'download'])->name('admin.backup.download');
        Route::delete('backup/{file}', [BackupController::class, 'destroy'])->name('admin.backup.destroy');

        // Target Routes
        Route::get('targets', [TargetController::class, 'index'])->name('admin.targets.index');
        Route::post('targets/team/{team}', [TargetController::class, 'setTeamTarget'])->name('admin.targets.team');
        Route::post('targets/user/{team}', [TargetController::class, 'setUserTarget'])->name('admin.targets.user');

        // PPC Routes
        Route::get('ppc', [PpcController::class, 'index'])->name('admin.ppc.index');
        Route::post('ppc', [PpcController::class, 'store'])->name('admin.ppc.store');
        Route::delete('ppc/{spending}', [PpcController::class, 'destroy'])->name('admin.ppc.destroy');

        Route::get('reports', [ReportController::class, 'index'])->name('admin.reports.index');
        Route::get('reports/company', [ReportController::class, 'company'])->name('admin.reports.company');
        Route::get('reports/team', [ReportController::class, 'team'])->name('admin.reports.team');
        Route::get('reports/sales', [ReportController::class, 'sales'])->name('admin.reports.sales');
        Route::get('reports/user', [ReportController::class, 'user'])->name('admin.reports.user');

        // Activity Logs (Admin only)
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');

        // Sales Routes
        Route::get('sales/datatable', [SaleController::class, 'datatable'])->name('admin.sales.datatable');
        Route::post('sales/{sale}/approve', [SaleController::class, 'approve'])->name('admin.sales.approve');
        Route::post('sales/{sale}/reject', [SaleController::class, 'reject'])->name('admin.sales.reject');
        Route::post('sales/{sale}/toggle-refund', [SaleController::class, 'toggleRefund'])->name('admin.sales.toggle-refund');
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
