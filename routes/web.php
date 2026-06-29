<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\OtpController;
use App\Http\Controllers\Admin\PendingRegistrationController;
use App\Http\Controllers\Admin\PpcController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\TargetController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Artisan;



Route::get('/clear-all', function () {
    Artisan::call('optimize:clear');
    return "All cache cleared!";
});

Route::get('/', fn () => redirect()->route('admin.login'));

Route::get('/admin/auth/hrm-sso', [AuthController::class, 'hrmSso'])->name('admin.hrm.sso');
Route::get('/admin/auth/global-logout', [AuthController::class, 'globalLogout'])->name('admin.global.logout');

Route::prefix('admin')->group(function () {

    // ── Guest ────────────────────────────────────────────────────────────────
    Route::get('/login',  [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout',[AuthController::class, 'logout'])->name('admin.logout');

    Route::middleware('guest')->group(function () {
        Route::post('otp/send',   [OtpController::class, 'sendOtp'])->name('admin.otp.send');
        Route::post('otp/resend', [OtpController::class, 'resendOtp'])->name('admin.otp.resend');
        Route::get('otp/verify',  [OtpController::class, 'showVerifyForm'])->name('admin.otp.verify.form');
        Route::post('otp/verify', [OtpController::class, 'verifyOtp'])->name('admin.otp.verify');
    });

    // ── Authenticated ────────────────────────────────────────────────────────
    Route::middleware(['auth', 'active.account', 'single.session'])->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

        // All roles: notifications + profile
        Route::get('notifications',           [NotificationController::class, 'index'])->name('admin.notifications.index');
        Route::get('notifications/poll',      [NotificationController::class, 'poll'])->name('admin.notifications.poll');
        Route::get('notifications/{id}/read', [NotificationController::class, 'follow'])->name('admin.notifications.follow');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('admin.notifications.read-all');

        Route::get('/profile',          [ProfileController::class, 'show'])->name('admin.profile.show');
        Route::put('/profile',          [ProfileController::class, 'updateProfile'])->name('admin.profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');

        // ── Admin only ───────────────────────────────────────────────────────
        Route::middleware('role:Admin')->group(function () {

            Route::get('users/datatable', [UserController::class, 'datatable'])->name('admin.users.datatable');
            Route::resource('users', UserController::class)->names([
                'index' => 'admin.users.index', 'create' => 'admin.users.create',
                'store' => 'admin.users.store', 'show'   => 'admin.users.show',
                'edit'  => 'admin.users.edit',  'update' => 'admin.users.update',
                'destroy' => 'admin.users.destroy',
            ]);

            Route::get('companies/datatable',       [CompanyController::class, 'datatable'])->name('admin.companies.datatable');
            Route::get('companies/{company}/teams', [CompanyController::class, 'teams'])->name('admin.companies.teams');
            Route::resource('companies', CompanyController::class)->names([
                'index' => 'admin.companies.index', 'create' => 'admin.companies.create',
                'store' => 'admin.companies.store', 'edit'   => 'admin.companies.edit',
                'update'=> 'admin.companies.update','destroy'=> 'admin.companies.destroy',
            ])->except(['show']);

            Route::get('brands/datatable', [BrandController::class, 'datatable'])->name('admin.brands.datatable');
            Route::resource('brands', BrandController::class)->names([
                'index'   => 'admin.brands.index',
                'create'  => 'admin.brands.create',
                'store'   => 'admin.brands.store',
                'show'    => 'admin.brands.show',
                'edit'    => 'admin.brands.edit',
                'update'  => 'admin.brands.update',
                'destroy' => 'admin.brands.destroy',
            ]);

            Route::get('teams/{team}/sub-teams', [TeamController::class, 'subTeams'])->name('admin.teams.sub-teams');
            Route::get('teams/{team}/sub-team-heads', [TeamController::class, 'subTeamHeads'])->name('admin.teams.sub-team-heads');

            Route::get('pending-registrations',                  [PendingRegistrationController::class, 'index'])->name('admin.pending-registrations.index');
            Route::post('pending-registrations/{user}/approve', [PendingRegistrationController::class, 'approve'])->name('admin.pending-registrations.approve');
            Route::post('pending-registrations/{user}/reject',  [PendingRegistrationController::class, 'reject'])->name('admin.pending-registrations.reject');

            Route::get('reports',         [ReportController::class, 'index'])->name('admin.reports.index');
            Route::get('reports/company', [ReportController::class, 'company'])->name('admin.reports.company');
            Route::get('reports/team',    [ReportController::class, 'team'])->name('admin.reports.team');
            Route::get('reports/sales',   [ReportController::class, 'sales'])->name('admin.reports.sales');
            Route::get('reports/user',    [ReportController::class, 'user'])->name('admin.reports.user');

            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
            Route::get('activity-logs/datatable', [ActivityLogController::class, 'datatable'])->name('admin.activity-logs.datatable');

            Route::get('settings',      [SettingController::class, 'index'])->name('admin.settings.index');
            Route::put('settings/otp',  [SettingController::class, 'updateOtp'])->name('admin.settings.otp');
            Route::put('settings/smtp', [SettingController::class, 'updateSmtp'])->name('admin.settings.smtp');

            Route::get('backup',                 [BackupController::class, 'index'])->name('admin.backup.index');
            Route::post('backup',                [BackupController::class, 'store'])->name('admin.backup.store');
            Route::get('backup/{file}/download', [BackupController::class, 'download'])->name('admin.backup.download');
            Route::delete('backup/{file}',       [BackupController::class, 'destroy'])->name('admin.backup.destroy');
        });

        // ── Admin + Agent + Team Head (non-PPC) ──────────────────────────────
        // Teams: Admin/Agent/Manager can view; controller handles edit/delete auth internally
        Route::middleware('role:Admin,Agent,Manager')->group(function () {
            Route::get('teams/datatable', [TeamController::class, 'datatable'])->name('admin.teams.datatable');
            Route::resource('teams', TeamController::class)->names([
                'index' => 'admin.teams.index', 'create' => 'admin.teams.create',
                'store' => 'admin.teams.store', 'show'   => 'admin.teams.show',
                'edit'  => 'admin.teams.edit',  'update' => 'admin.teams.update',
                'destroy' => 'admin.teams.destroy',
            ]);

            Route::get('targets',                      [TargetController::class, 'index'])->name('admin.targets.index');
            Route::post('targets/team/{team}',         [TargetController::class, 'setTeamTarget'])->name('admin.targets.team');
            Route::post('targets/sub-team/{team}',     [TargetController::class, 'setSubTeamHeadTarget'])->name('admin.targets.sub-team');
            Route::post('targets/user/{team}',         [TargetController::class, 'setUserTarget'])->name('admin.targets.user');

            Route::get('sales/datatable',             [SaleController::class, 'datatable'])->name('admin.sales.datatable');
            Route::get('sales/{sale}/brief-document/{brandBriefForm}', [SaleController::class, 'downloadBriefDocument'])->name('admin.sales.brief-document');
            Route::post('sales/{sale}/approve',       [SaleController::class, 'approve'])->name('admin.sales.approve');
            Route::post('sales/{sale}/reject',        [SaleController::class, 'reject'])->name('admin.sales.reject');
            Route::post('sales/{sale}/toggle-refund', [SaleController::class, 'toggleRefund'])->name('admin.sales.toggle-refund');
            Route::delete('sales/{sale}',             [SaleController::class, 'destroy'])->name('admin.sales.destroy');
            Route::resource('sales', SaleController::class)->names([
                'index'  => 'admin.sales.index',  'create' => 'admin.sales.create',
                'store'  => 'admin.sales.store',  'show'   => 'admin.sales.show',
                'edit'   => 'admin.sales.edit',   'update' => 'admin.sales.update',
            ])->except(['destroy']);
        });

        // ── PPC + Admin ──────────────────────────────────────────────────────
        Route::middleware('role:Admin,PPC')->group(function () {
            Route::get('ppc',               [PpcController::class, 'index'])->name('admin.ppc.index');
            Route::post('ppc',              [PpcController::class, 'store'])->name('admin.ppc.store');
            Route::delete('ppc/{spending}', [PpcController::class, 'destroy'])->name('admin.ppc.destroy');
        });
    });
});
