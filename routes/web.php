<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\OtpController;
use App\Http\Controllers\Admin\PendingRegistrationController;
use App\Http\Controllers\Admin\PpcController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\BriefFormBuilderController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\TargetController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamMemberController;
use App\Http\Controllers\Admin\TeamMembershipController;
use App\Http\Controllers\Admin\TrelloCardController;
use App\Http\Controllers\Admin\TrelloController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\TrelloWebhookController;



Route::get('/', fn () => redirect()->route('admin.login'));

Route::match(['get', 'head', 'post'], '/webhooks/trello/{team}/{secret}', [TrelloWebhookController::class, 'handle'])
    ->name('webhooks.trello')
    ->whereNumber('team');

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
        Route::get('/search', GlobalSearchController::class)->name('admin.search');

        // All roles: notifications + profile
        Route::get('notifications',           [NotificationController::class, 'index'])->name('admin.notifications.index');
        Route::get('notifications/poll',      [NotificationController::class, 'poll'])->name('admin.notifications.poll');
        Route::get('notifications/{id}/read', [NotificationController::class, 'follow'])->name('admin.notifications.follow');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('admin.notifications.read-all');

        Route::get('/profile',          [ProfileController::class, 'show'])->name('admin.profile.show');
        Route::put('/profile',          [ProfileController::class, 'updateProfile'])->name('admin.profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');

        // ── Clients: any authenticated role (Admin, Manager, Team Head, Agent, Project Manager) ──
        Route::middleware('role:Admin,Manager,Agent,Project Manager')->group(function () {
            Route::get('clients/datatable',      [ClientController::class, 'datatable'])->name('admin.clients.datatable');
            Route::get('clients/{client}/lookup', [ClientController::class, 'lookup'])->name('admin.clients.lookup');
            Route::resource('clients', ClientController::class)->names([
                'index'  => 'admin.clients.index',  'create' => 'admin.clients.create',
                'store'  => 'admin.clients.store',   'show'   => 'admin.clients.show',
                'edit'   => 'admin.clients.edit',    'update' => 'admin.clients.update',
                'destroy'=> 'admin.clients.destroy',
            ]);
        });

        // ── Project Manager: team directory + join/leave (Requirements 2, 3, 5) ──
        Route::middleware('role:Project Manager')->group(function () {
            Route::get('team-memberships',              [TeamMembershipController::class, 'index'])->name('admin.team-memberships.index');
            Route::post('team-memberships/{team}/join', [TeamMembershipController::class, 'join'])->name('admin.team-memberships.join');
            Route::delete('team-memberships/{team}/leave', [TeamMembershipController::class, 'leave'])->name('admin.team-memberships.leave');
        });

        // ── Team Head (+ Admin/Manager): approve/reject join requests ──
        Route::middleware('role:Admin,Manager,Agent')->group(function () {
            Route::get('team-join-requests',                    [TeamMembershipController::class, 'requestsIndex'])->name('admin.teams.join-requests.index');
            Route::post('team-join-requests/{membership}/approve', [TeamMembershipController::class, 'approve'])->name('admin.teams.join-requests.approve');
            Route::post('team-join-requests/{membership}/reject',  [TeamMembershipController::class, 'reject'])->name('admin.teams.join-requests.reject');
        });

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

            Route::post('brands/{brand}/brief-forms', [BrandController::class, 'storeBriefForm'])
                ->name('admin.brands.brief-forms.store');
            Route::delete('brands/{brand}/brief-forms/{form}', [BrandController::class, 'destroyBriefForm'])
                ->name('admin.brands.brief-forms.destroy');
            Route::get('brands/{brand}/brief-forms/{form}/builder', [BriefFormBuilderController::class, 'show'])
                ->name('admin.brands.brief-forms.builder');
            Route::put('brands/{brand}/brief-forms/{form}/builder', [BriefFormBuilderController::class, 'update'])
                ->name('admin.brands.brief-forms.builder.update');
            Route::get('brands/{brand}/brief-forms/{form}/builder/preview', [BriefFormBuilderController::class, 'preview'])
                ->name('admin.brands.brief-forms.builder.preview');

            Route::get('teams/{team}/sub-teams', [TeamController::class, 'subTeams'])->name('admin.teams.sub-teams');
            Route::get('teams/{team}/sub-team-heads', [TeamController::class, 'subTeamHeads'])->name('admin.teams.sub-team-heads');

            Route::get('pending-registrations',                  [PendingRegistrationController::class, 'index'])->name('admin.pending-registrations.index');
            Route::post('pending-registrations/{user}/approve', [PendingRegistrationController::class, 'approve'])->name('admin.pending-registrations.approve');
            Route::post('pending-registrations/{user}/reject',  [PendingRegistrationController::class, 'reject'])->name('admin.pending-registrations.reject');

            Route::get('settings',      [SettingController::class, 'index'])->name('admin.settings.index');
            Route::put('settings/otp',  [SettingController::class, 'updateOtp'])->name('admin.settings.otp');
            Route::put('settings/smtp', [SettingController::class, 'updateSmtp'])->name('admin.settings.smtp');

            Route::get('backup',                 [BackupController::class, 'index'])->name('admin.backup.index');
            Route::post('backup',                [BackupController::class, 'store'])->name('admin.backup.store');
            Route::get('backup/{file}/download', [BackupController::class, 'download'])->name('admin.backup.download');
            Route::delete('backup/{file}',       [BackupController::class, 'destroy'])->name('admin.backup.destroy');

            Route::resource('expenses', ExpenseController::class)->names([
                'index' => 'admin.expenses.index', 'create' => 'admin.expenses.create',
                'store' => 'admin.expenses.store', 'edit' => 'admin.expenses.edit',
                'update' => 'admin.expenses.update', 'destroy' => 'admin.expenses.destroy',
            ])->except(['show']);
        });

        // ── Admin + Manager: teams CRUD, activity ────────────────────────────
        Route::middleware('role:Admin,Manager')->group(function () {
            Route::get('teams/datatable', [TeamController::class, 'datatable'])->name('admin.teams.datatable');
            Route::resource('teams', TeamController::class)->names([
                'index' => 'admin.teams.index', 'create' => 'admin.teams.create',
                'store' => 'admin.teams.store',
                'edit'  => 'admin.teams.edit',  'update' => 'admin.teams.update',
                'destroy' => 'admin.teams.destroy',
            ])->except(['show']);

            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
            Route::get('activity-logs/datatable', [ActivityLogController::class, 'datatable'])->name('admin.activity-logs.datatable');
        });

        // ── Reports: Admin/Manager (all) + Team Lead (own team scoped). Agents blocked in controller.
        Route::middleware('role:Admin,Manager,Agent')->group(function () {
            Route::get('reports',          [ReportController::class, 'index'])->name('admin.reports.index');
            Route::get('reports/company',  [ReportController::class, 'company'])->name('admin.reports.company');
            Route::get('reports/team',     [ReportController::class, 'team'])->name('admin.reports.team');
            Route::get('reports/sales',    [ReportController::class, 'sales'])->name('admin.reports.sales');
            Route::get('reports/user',     [ReportController::class, 'user'])->name('admin.reports.user');
            Route::get('reports/leads',    [ReportController::class, 'leads'])->name('admin.reports.leads');
            Route::get('reports/invoices', [ReportController::class, 'invoices'])->name('admin.reports.invoices');
            Route::get('reports/clients',  [ReportController::class, 'clients'])->name('admin.reports.clients');
            Route::get('reports/brands',   [ReportController::class, 'brands'])->name('admin.reports.brands');
            Route::get('reports/expenses', [ReportController::class, 'expenses'])->name('admin.reports.expenses');
        });

        // ── Admin + Agent + Manager: Sales Performance (targets) ─────────────
        Route::middleware('role:Admin,Agent,Manager')->group(function () {
            Route::get('targets',                      [TargetController::class, 'index'])->name('admin.targets.index');
            Route::post('targets/team/{team}',         [TargetController::class, 'setTeamTarget'])->name('admin.targets.team');
            Route::post('targets/sub-team/{team}',     [TargetController::class, 'setSubTeamHeadTarget'])->name('admin.targets.sub-team');
            Route::post('targets/user/{team}',         [TargetController::class, 'setUserTarget'])->name('admin.targets.user');
            Route::post('targets/user/{team}/bulk',    [TargetController::class, 'bulkSetUserTargets'])->name('admin.targets.user.bulk');
        });

        // ── Trello boards (view): Admin / Manager / Agent / Project Manager ───
        Route::middleware('role:Admin,Agent,Manager,Project Manager')->group(function () {
            Route::get('trello', [TrelloController::class, 'index'])->name('admin.trello.index');
            Route::get('trello/boards/{boardId}', [TrelloController::class, 'board'])->name('admin.trello.board');
            Route::get('trello/teams/{team}/boards/{boardId}/live', [TrelloController::class, 'live'])
                ->name('admin.trello.board.live')
                ->whereNumber('team');
            Route::post('trello/teams/{team}/boards/{boardId}/live/ensure', [TrelloController::class, 'ensureLive'])
                ->name('admin.trello.board.live.ensure')
                ->whereNumber('team');
            Route::get('trello/teams/{team}/boards/{boardId}/tick', [TrelloController::class, 'tick'])
                ->name('admin.trello.board.tick')
                ->whereNumber('team');
            Route::get('trello/teams/{team}/covers/{cardId}/{attachmentId}/{previewId}', [TrelloController::class, 'cover'])
                ->name('admin.trello.cover')
                ->whereNumber('team');
            Route::post('trello/teams/{team}/cards/{cardId}/move', [TrelloController::class, 'moveCard'])
                ->name('admin.trello.move-card')
                ->whereNumber('team');
            Route::get('trello/teams/{team}/move-targets', [TrelloController::class, 'moveTargets'])
                ->name('admin.trello.move-targets')
                ->whereNumber('team');
            Route::get('trello/link-preview', [TrelloController::class, 'linkPreview'])
                ->name('admin.trello.link-preview');

            // Card detail actions (comments, checklists, labels, members, attachments…)
            Route::post('trello/teams/{team}/cards', [TrelloCardController::class, 'store'])->name('admin.trello.cards.store')->whereNumber('team');
            Route::get('trello/teams/{team}/cards/{cardId}', [TrelloCardController::class, 'show'])->name('admin.trello.cards.show')->whereNumber('team');
            Route::put('trello/teams/{team}/cards/{cardId}', [TrelloCardController::class, 'update'])->name('admin.trello.cards.update')->whereNumber('team');
            Route::post('trello/teams/{team}/cards/{cardId}/comments', [TrelloCardController::class, 'comment'])->name('admin.trello.cards.comment')->whereNumber('team');
            Route::put('trello/teams/{team}/comments/{actionId}', [TrelloCardController::class, 'updateComment'])->name('admin.trello.comments.update')->whereNumber('team');
            Route::delete('trello/teams/{team}/comments/{actionId}', [TrelloCardController::class, 'deleteComment'])->name('admin.trello.comments.destroy')->whereNumber('team');
            Route::post('trello/teams/{team}/comments/{actionId}/reactions', [TrelloCardController::class, 'addReaction'])->name('admin.trello.comments.reactions')->whereNumber('team');
            Route::post('trello/teams/{team}/cards/{cardId}/attach-url', [TrelloCardController::class, 'attachUrl'])->name('admin.trello.attachments.url')->whereNumber('team');
            Route::post('trello/teams/{team}/cards/{cardId}/labels', [TrelloCardController::class, 'toggleLabel'])->name('admin.trello.cards.labels')->whereNumber('team');
            Route::post('trello/teams/{team}/cards/{cardId}/labels/create', [TrelloCardController::class, 'createLabel'])->name('admin.trello.labels.create')->whereNumber('team');
            Route::put('trello/teams/{team}/labels/{labelId}', [TrelloCardController::class, 'updateLabel'])->name('admin.trello.labels.update')->whereNumber('team');
            Route::post('trello/teams/{team}/cards/{cardId}/members', [TrelloCardController::class, 'toggleMember'])->name('admin.trello.cards.members')->whereNumber('team');
            Route::post('trello/teams/{team}/cards/{cardId}/checklists', [TrelloCardController::class, 'createChecklist'])->name('admin.trello.cards.checklists')->whereNumber('team');
            Route::delete('trello/teams/{team}/checklists/{checklistId}', [TrelloCardController::class, 'deleteChecklist'])->name('admin.trello.checklists.destroy')->whereNumber('team');
            Route::post('trello/teams/{team}/checklists/{checklistId}/items', [TrelloCardController::class, 'addCheckItem'])->name('admin.trello.checkitems.store')->whereNumber('team');
            Route::put('trello/teams/{team}/cards/{cardId}/checkitems/{checkItemId}', [TrelloCardController::class, 'updateCheckItem'])->name('admin.trello.checkitems.update')->whereNumber('team');
            Route::delete('trello/teams/{team}/checklists/{checklistId}/items/{checkItemId}', [TrelloCardController::class, 'deleteCheckItem'])->name('admin.trello.checkitems.destroy')->whereNumber('team');
            Route::post('trello/teams/{team}/cards/{cardId}/attachments', [TrelloCardController::class, 'uploadAttachment'])->name('admin.trello.attachments.store')->whereNumber('team');
            Route::get('trello/teams/{team}/cards/{cardId}/attachments/{attachmentId}', [TrelloCardController::class, 'attachment'])->name('admin.trello.attachment')->whereNumber('team');
            Route::put('trello/teams/{team}/cards/{cardId}/attachments/{attachmentId}', [TrelloCardController::class, 'updateAttachment'])->name('admin.trello.attachments.update')->whereNumber('team');
            Route::put('trello/teams/{team}/cards/{cardId}/cover', [TrelloCardController::class, 'setCover'])->name('admin.trello.cards.cover')->whereNumber('team');
            Route::delete('trello/teams/{team}/cards/{cardId}/attachments/{attachmentId}', [TrelloCardController::class, 'deleteAttachment'])->name('admin.trello.attachments.destroy')->whereNumber('team');
        });
        // ── Trello credentials: Admin only ───────────────────────────────────
        Route::middleware('role:Admin')->group(function () {
            Route::get('trello/connections', [TrelloController::class, 'connections'])->name('admin.trello.connections');
            Route::get('trello/teams/{team}/connect', [TrelloController::class, 'edit'])->name('admin.trello.edit')->whereNumber('team');
            Route::post('trello/teams/{team}/connect', [TrelloController::class, 'store'])->name('admin.trello.store')->whereNumber('team');
            Route::post('trello/teams/{team}/verify', [TrelloController::class, 'verify'])->name('admin.trello.verify')->whereNumber('team');
            Route::post('trello/teams/{team}/sync-customers', [TrelloController::class, 'syncCustomers'])->name('admin.trello.sync-customers')->whereNumber('team');
            Route::delete('trello/teams/{team}/connect', [TrelloController::class, 'destroy'])->name('admin.trello.destroy')->whereNumber('team');
        });

        // ── Admin + Agent + Team Head + Project Manager: sales & invoices ────
        // (Requirements 6, 7 — Project Manager gets scoped access via TeamMembership)
        Route::middleware('role:Admin,Agent,Manager,Project Manager')->group(function () {
            Route::get('sales/datatable',             [SaleController::class, 'datatable'])->name('admin.sales.datatable');
            Route::post('sales/{sale}/invoices',      [InvoiceController::class, 'store'])->name('admin.invoices.store');
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

            Route::get('leads/datatable', [LeadController::class, 'datatable'])->name('admin.leads.datatable');
            Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('admin.leads.convert');
            Route::post('leads/{lead}/activities', [LeadController::class, 'storeActivity'])->name('admin.leads.activities.store')->whereNumber('lead');
            Route::resource('leads', LeadController::class)->names([
                'index' => 'admin.leads.index', 'create' => 'admin.leads.create',
                'store' => 'admin.leads.store', 'show' => 'admin.leads.show',
                'edit' => 'admin.leads.edit', 'update' => 'admin.leads.update',
                'destroy' => 'admin.leads.destroy',
            ]);

            Route::get('sales-performance', fn () => redirect()->route('admin.targets.index'))->name('admin.sales-performance.index');

            Route::get('invoices/create', [InvoiceController::class, 'create'])->name('admin.invoices.create');
            Route::post('invoices', [InvoiceController::class, 'storeStandalone'])->name('admin.invoices.store-standalone');
            Route::get('invoices/datatable', [InvoiceController::class, 'datatable'])->name('admin.invoices.datatable');
            Route::get('invoices',           [InvoiceController::class, 'index'])->name('admin.invoices.index');
            Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('admin.invoices.edit')->whereNumber('invoice');
            Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('admin.invoices.update')->whereNumber('invoice');
            Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('admin.invoices.issue')->whereNumber('invoice');
            Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('admin.invoices.payment')->whereNumber('invoice');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('admin.invoices.show')->whereNumber('invoice');
            Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('admin.invoices.pdf')->whereNumber('invoice');
            Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('admin.invoices.void')->whereNumber('invoice');
        });

        // ── Team show: PPC + Project Manager can view (scoped) ───────────────
        Route::middleware('role:Admin,Agent,Manager,PPC,Project Manager')->group(function () {
            Route::get('teams/{team}', [TeamController::class, 'show'])->name('admin.teams.show')->whereNumber('team');
        });

        // ── Team members: Team Lead can view; only Admin can add/remove ───────
        Route::middleware('role:Admin,Agent,Manager')->group(function () {
            Route::get('teams/{team}/members', [TeamMemberController::class, 'index'])->name('admin.teams.members.index')->whereNumber('team');
        });
        Route::middleware('role:Admin')->group(function () {
            Route::post('teams/{team}/members', [TeamMemberController::class, 'storeExisting'])->name('admin.teams.members.store')->whereNumber('team');
            Route::post('teams/{team}/members/create', [TeamMemberController::class, 'storeNew'])->name('admin.teams.members.create')->whereNumber('team');
            Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('admin.teams.members.destroy')->whereNumber(['team', 'user']);
        });

        // ── PPC + Admin ──────────────────────────────────────────────────────
        Route::middleware('role:Admin,PPC')->group(function () {
            Route::get('ppc',               [PpcController::class, 'index'])->name('admin.ppc.index');
            Route::post('ppc',              [PpcController::class, 'store'])->name('admin.ppc.store');
            Route::delete('ppc/{spending}', [PpcController::class, 'destroy'])->name('admin.ppc.destroy');
        });
    });
});
