<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandBriefForm;
use App\Models\BriefSubmission;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use App\Services\InvoiceService;
use App\Services\SaleNotificationDispatcher;
use App\Support\AuthScope;
use App\Services\BriefFormSchemaService;
use App\Support\BriefFormSupport;
use App\Support\BriefSubmissionDisplay;
use App\Support\BriefSubmissionLabels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SaleController extends Controller
{
    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function isTeamHead(): bool
    {
        return Team::headedBy(Auth::id())->exists();
    }

    private function isAgent(): bool
    {
        return Auth::user()->hasRole('Agent') && ! $this->isTeamHead();
    }

    private function isProjectManager(): bool
    {
        return Auth::user()->hasRole(Role::PROJECT_MANAGER);
    }

    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user  = Auth::user();
        $query = Sale::query()->with(['user', 'team', 'company', 'brand'])->select('sales.*')
            ->where('is_draft', false);

        if (AuthScope::seesAllMoney()) {
            // Admin, Manager, Finance: all sales
        } elseif ($this->isProjectManager()) {
            // Requirement 6.1/6.2 — Project Manager: only sales from their approved (joined) teams
            $query->whereIn('team_id', $user->approvedTeamIdsWithSubTeams()); // sub-teams too, like clients
        } elseif ($this->isTeamHead()) {
            // Team Head: sales from their teams
            $teamIds = Team::headedBy($user->id)->pluck('id');
            $query->whereIn('team_id', $teamIds);
        } else {
            $subTeamHeadIds = \App\Models\SubTeamHead::where('user_id', $user->id)->pluck('id');

            if ($subTeamHeadIds->isNotEmpty()) {
                // Sub-Team Head: own sales + all members under any of their seats + unassigned on team
                $subTeamMemberIds = \App\Models\User::whereIn('sub_team_head_id', $subTeamHeadIds)
                    ->pluck('id')
                    ->push($user->id)
                    ->unique()
                    ->values()
                    ->all();

                $query->where(function ($q) use ($subTeamMemberIds, $user) {
                    $q->whereIn('user_id', $subTeamMemberIds);
                    if ($user->team_id) {
                        $q->orWhere(function ($q2) use ($user) {
                            $q2->whereNull('user_id')->where('team_id', $user->team_id);
                        });
                    }
                });
            } else {
                // Regular Agent: own sales + unassigned imports on their team
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if ($user->team_id) {
                        $q->orWhere(function ($q2) use ($user) {
                            $q2->whereNull('user_id')->where('team_id', $user->team_id);
                        });
                    }
                });
            }
        }

        return $query;
    }

    /** Refunds / chargebacks need the sale approver (Admin / Manager / Team Head); Finance only sees them. */
    private function canToggleRefund(Sale $sale): bool
    {
        return $this->canApprove($sale);
    }

    private function canToggleChargeback(Sale $sale): bool
    {
        return $this->canApprove($sale);
    }

    private function canApprove(Sale $sale): bool
    {
        $user = Auth::user();
        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return true;
        }
        // Team lead approves every sale of their team, their own included
        if ($sale->team_id && Team::headedBy($user->id)->where('id', $sale->team_id)->exists()) {
            return true;
        }

        // Sub-team head may approve members' sales — never their own.
        if ((int) $sale->user_id === (int) $user->id) {
            return false;
        }

        $subTeamHeadIds = \App\Models\SubTeamHead::where('user_id', $user->id)->pluck('id');
        if ($subTeamHeadIds->isEmpty()) {
            return false;
        }

        $saleUser = \App\Models\User::find($sale->user_id);

        return $saleUser && $subTeamHeadIds->contains((int) $saleUser->sub_team_head_id);
    }

    // ── Index ────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $user    = Auth::user();
        $isAgent = $this->isAgent();

        $teams            = AuthScope::teamsForDropdown();
        $users            = $isAgent ? collect() : AuthScope::usersForDropdown();
        $brands           = $this->brandsForDropdown();
        $pendingCount     = $this->baseQuery()->where('approval_status', Sale::APPROVAL_PENDING)->count();
        $isProjectManager = $this->isProjectManager();
        $canBulkApprove   = $this->userCanBulkApprove();
        $canBulkDelete    = ! $user->hasRole(Role::FINANCE);

        return view('admin.sales.index', compact(
            'teams', 'users', 'brands', 'isAgent', 'pendingCount', 'isProjectManager',
            'canBulkApprove', 'canBulkDelete'
        ));
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery();

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }
        if ($request->filled('sale_type')) {
            $query->where('sale_type', $request->sale_type);
        }
        if ($request->filled('service_category')) {
            $query->where('service_category', $request->service_category);
        }
        if ($request->filled('refunded')) {
            if ($request->refunded === '1') {
                $query->where('is_refunded', true);
            } elseif ($request->refunded === '0') {
                $query->where('is_refunded', false);
            }
        }
        if ($request->filled('chargeback')) {
            if ($request->chargeback === '1') {
                $query->where('is_chargeback', true);
            } elseif ($request->chargeback === '0') {
                $query->where('is_chargeback', false);
            }
        }
        if ($request->filled('received_filter')) {
            match ($request->received_filter) {
                'none'    => $query->where('received_amount', 0),
                'partial' => $query->where('received_amount', '>', 0)
                    ->whereColumn('received_amount', '<', 'amount'),
                'full'    => $query->whereColumn('received_amount', '>=', 'amount'),
                default   => null,
            };
        }
        if ($request->filled('remaining_filter')) {
            match ($request->remaining_filter) {
                'zero'        => $query->whereRaw('(amount - received_amount) <= 0'),
                'outstanding' => $query->whereRaw('(amount - received_amount) > 0'),
                default       => null,
            };
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->filled('month') && ctype_digit((string) $request->month)) {
            $month = (int) $request->month;
            if ($month >= 1 && $month <= 12) {
                $query->whereMonth('sale_date', $month);
            }
        }
        if ($request->filled('year') && ctype_digit((string) $request->year)) {
            $year = (int) $request->year;
            if ($year >= 2000 && $year <= 2100) {
                $query->whereYear('sale_date', $year);
            }
        }

        return DataTables::eloquent($query)
            ->editColumn('amount', function (Sale $s) {
                $fmt = '$' . number_format($s->amount, 2);
                if ($s->is_refunded || $s->is_chargeback) {
                    return '<span class="text-danger">' . e($fmt) . '</span>';
                }

                return $fmt;
            })
            ->editColumn('received_amount', fn (Sale $s) => '$' . number_format($s->received_amount, 2))
            ->addColumn('remaining_amount', function (Sale $s) {
                $remaining = $s->remainingAmount();
                $class = $remaining > 0 ? 'text-warning' : 'text-success';

                return '<span class="' . $class . '">$' . number_format($remaining, 2) . '</span>';
            })
            ->orderColumn('remaining_amount', function ($query, $direction) {
                $query->orderByRaw('(amount - received_amount) ' . ($direction === 'desc' ? 'desc' : 'asc'));
            })
            ->editColumn('sale_date', fn (Sale $s) => $s->sale_date->format('d M Y'))
            ->addColumn('sale_type_badge', fn (Sale $s) =>
                '<span class="badge bg-' . self::saleTypeColor($s->sale_type) . '">' . e($s->saleTypeLabel()) . '</span>')
            ->addColumn('service_category_badge', function (Sale $s) {
                if (! $s->service_category) {
                    return '<span class="text-muted">—</span>';
                }
                $color = $s->service_category === Sale::CATEGORY_DESIGN ? 'warning' : 'secondary';

                return '<span class="badge bg-'.$color.'">'.e($s->serviceCategoryLabel()).'</span>';
            })
            ->addColumn('refund_toggle', function (Sale $sale) {
                $refundAmt = $sale->is_refunded
                    ? '$'.number_format((float) ($sale->refunded_amount ?? $sale->amount), 2)
                    : null;

                if (! $this->canToggleRefund($sale)) {
                    return $sale->is_refunded
                        ? '<span class="badge bg-danger" title="Refunded amount">Refunded '.$refundAmt.'</span>'
                        : '<span class="text-muted small">—</span>';
                }

                $url = route('admin.sales.toggle-refund', $sale);
                $checked = $sale->is_refunded ? 'checked' : '';
                $csrf = csrf_token();
                $max = number_format((float) $sale->amount, 2, '.', '');
                $default = number_format(
                    (float) ($sale->received_amount > 0 ? $sale->received_amount : $sale->amount),
                    2,
                    '.',
                    ''
                );
                $label = $sale->is_refunded
                    ? '<div class="small text-danger mt-1">'.$refundAmt.'</div>'
                    : '';

                return '<form action="'.$url.'" method="POST" class="d-inline js-refund-toggle-form"'
                    .' data-sale-id="'.$sale->id.'"'
                    .' data-max="'.$max.'"'
                    .' data-default="'.$default.'"'
                    .' data-refunded="'.($sale->is_refunded ? '1' : '0').'">'
                    .'<input type="hidden" name="_token" value="'.$csrf.'">'
                    .'<input type="hidden" name="refunded_amount" value="">'
                    .'<div class="form-check form-switch mb-0 d-flex justify-content-center">'
                    .'<input class="form-check-input js-refund-switch" type="checkbox" role="switch" '.$checked
                    .' title="Mark refunded / revert" aria-label="Refunded">'
                    .'</div>'.$label.'</form>';
            })
            ->addColumn('chargeback_toggle', function (Sale $sale) {
                $cbAmt = $sale->is_chargeback
                    ? '$'.number_format((float) ($sale->chargeback_amount ?? $sale->amount), 2)
                    : null;

                if (! $this->canToggleChargeback($sale)) {
                    return $sale->is_chargeback
                        ? '<span class="badge bg-dark" title="Chargeback amount">Chargeback '.$cbAmt.'</span>'
                        : '<span class="text-muted small">—</span>';
                }

                $url = route('admin.sales.toggle-chargeback', $sale);
                $checked = $sale->is_chargeback ? 'checked' : '';
                $csrf = csrf_token();
                $max = number_format((float) $sale->amount, 2, '.', '');
                $default = number_format(
                    (float) ($sale->received_amount > 0 ? $sale->received_amount : $sale->amount),
                    2,
                    '.',
                    ''
                );
                $label = $sale->is_chargeback
                    ? '<div class="small text-danger mt-1">'.$cbAmt.'</div>'
                    : '';

                return '<form action="'.$url.'" method="POST" class="d-inline js-chargeback-toggle-form"'
                    .' data-sale-id="'.$sale->id.'"'
                    .' data-max="'.$max.'"'
                    .' data-default="'.$default.'"'
                    .' data-chargeback="'.($sale->is_chargeback ? '1' : '0').'">'
                    .'<input type="hidden" name="_token" value="'.$csrf.'">'
                    .'<input type="hidden" name="chargeback_amount" value="">'
                    .'<div class="form-check form-switch mb-0 d-flex justify-content-center">'
                    .'<input class="form-check-input js-chargeback-switch" type="checkbox" role="switch" '.$checked
                    .' title="Mark chargeback / revert" aria-label="Chargeback">'
                    .'</div>'.$label.'</form>';
            })
            ->editColumn('status', fn (Sale $s) =>
                '<span class="badge bg-' . self::statusColor($s->status) . '">' . e($s->statusLabel()) . '</span>')
            ->addColumn('approval_badge', fn (Sale $s) =>
                '<span class="badge bg-' . self::approvalColor($s->approval_status) . '">' . self::approvalLabel($s->approval_status) . '</span>')
            ->addColumn('agent_name', function (Sale $s) {
                if ($s->user) {
                    return e($s->user->name);
                }
                if ($s->sales_agent_name) {
                    return e($s->sales_agent_name).' <span class="badge bg-secondary" title="Name from import — no CRM login">Former</span>';
                }

                return '<span class="badge bg-warning text-dark">Unassigned</span>';
            })
            ->addColumn('team_name', fn (Sale $s) => e($s->team?->name ?? '-'))
            ->addColumn('brand_name', fn (Sale $s) => e($s->brand?->name ?? '-'))
            ->addColumn('select', function (Sale $sale) {
                $canApprove = $this->canApprove($sale) && $sale->isPendingApproval();
                $canDelete = $this->userCanDeleteSale($sale);
                if (! $canApprove && ! $canDelete) {
                    return '';
                }

                return '<input type="checkbox" class="form-check-input sale-row-check" value="' . (int) $sale->id . '"'
                    . ' data-can-approve="' . ($canApprove ? '1' : '0') . '"'
                    . ' data-can-delete="' . ($canDelete ? '1' : '0') . '"'
                    . ' aria-label="Select sale #' . (int) $sale->id . '">';
            })
            ->addColumn('actions', function (Sale $sale) {
                $canEdit   = $this->userCanEditSale($sale);
                $canDelete = $this->userCanDeleteSale($sale);
                $canApprove = $this->canApprove($sale) && $sale->isPendingApproval();
                $canReject  = $this->canApprove($sale) && $sale->isPendingApproval();

                $showUrl    = route('admin.sales.show', $sale);
                $editUrl    = route('admin.sales.edit', $sale);
                $deleteUrl  = route('admin.sales.destroy', $sale);
                $approveUrl = route('admin.sales.approve', $sale);
                $rejectUrl  = route('admin.sales.reject', $sale);
                $csrf       = csrf_field();
                $method     = method_field('DELETE');

                $html = '<a href="' . $showUrl . '" class="btn btn-sm btn-outline-info">View</a>';

                if ($canApprove) {
                    $html .= '
                    <form action="' . $approveUrl . '" method="POST" class="d-inline">
                        ' . $csrf . '
                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                    </form>';
                }
                if ($canReject) {
                    $html .= '
                    <button type="button" class="btn btn-sm btn-danger btn-reject"
                        data-url="' . $rejectUrl . '">Reject</button>';
                }

                if ($canEdit) {
                    $html .= ' <a href="' . $editUrl . '" class="btn btn-sm btn-outline-warning">Edit</a>';
                }
                if ($canDelete) {
                    $html .= '
                    <form action="' . $deleteUrl . '" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this sale?">
                        ' . $csrf . $method . '
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>';
                }
                return $html;
            })
            ->rawColumns(['select', 'amount', 'remaining_amount', 'sale_type_badge', 'service_category_badge', 'refund_toggle', 'chargeback_toggle', 'status', 'approval_badge', 'agent_name', 'actions'])
            ->toJson();
    }

    public function bulkApprove(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->userCanBulkApprove(), 403);

        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $approved = 0;
        $skipped = 0;
        $sales = $this->baseQuery()->whereIn('id', $ids)->get();

        foreach ($sales as $sale) {
            if (! $this->canApprove($sale) || ! $sale->isPendingApproval()) {
                $skipped++;
                continue;
            }
            $this->applyApproval($sale);
            $approved++;
        }

        $msg = "Approved {$approved} sale(s).";
        if ($skipped > 0) {
            $msg .= " {$skipped} skipped (no permission or already decided).";
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'approved' => $approved, 'skipped' => $skipped, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    public function bulkDestroy(Request $request): RedirectResponse|JsonResponse
    {
        abort_if(Auth::user()->hasRole(Role::FINANCE), 403);

        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $deleted = 0;
        $skipped = 0;
        $sales = $this->baseQuery()->whereIn('id', $ids)->get();

        foreach ($sales as $sale) {
            if (! $this->userCanDeleteSale($sale)) {
                $skipped++;
                continue;
            }
            ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_SALE_DELETED,
                "Deleted sale \"{$sale->title}\" (#{$sale->id})",
                ['sale_id' => $sale->id, 'amount' => $sale->amount]
            );
            $sale->delete();
            $deleted++;
        }

        $msg = "Deleted {$deleted} sale(s).";
        if ($skipped > 0) {
            $msg .= " {$skipped} skipped (no permission or already approved).";
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'deleted' => $deleted, 'skipped' => $skipped, 'message' => $msg]);
        }

        return redirect()->route('admin.sales.index')->with('success', $msg);
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        $user = Auth::user();

        if ($user->hasRole(Role::ADMIN)) {
            abort(403);
        }

        if ($this->isProjectManager() && $user->approvedTeamIds()->isEmpty()) {
            abort(403, 'Join a team before creating sales.');
        }

        $lead = null;
        $prefill = [];
        if ($request->filled('lead_id')) {
            $lead = \App\Models\Lead::query()->find((int) $request->lead_id);
            if ($lead && AuthScope::canAccessLead($lead)) {
                $prefill = [
                    'lead_id' => $lead->id,
                    'client_id' => $lead->client_id ?: $request->get('client_id'),
                    'client_name' => $lead->company ?: $lead->name,
                    'client_email' => $lead->email,
                    'client_phone' => $lead->phone,
                    'brand_id' => $lead->brand_id,
                    'amount' => $lead->lead_value,
                    'title' => 'Sale — ' . ($lead->company ?: $lead->name),
                ];
            } else {
                $lead = null;
            }
        }

        return view('admin.sales.create', [
            'brands'           => $this->brandsForDropdown(),
            'clients'          => \App\Http\Controllers\Admin\ClientController::forDropdown(),
            'staffUsers'       => AuthScope::usersForDropdown(),
            'isProjectManager' => $this->isProjectManager(),
            'joinedTeams'      => $this->isProjectManager() ? AuthScope::teamsForDropdown() : collect(),
            'suggestedSaleType'=> $this->suggestedSaleType($user),
            'leadPrefill'      => $prefill,
            'fromLead'         => $lead,
        ]);
    }

    public function downloadBriefDocument(Sale $sale, BrandBriefForm $brandBriefForm)
    {
        $this->authorizeView($sale);
        abort_if(Auth::user()->hasRole(Role::FINANCE), 403, 'Client briefs are not available to Finance.');

        $sale->loadMissing('brand');

        if (! $sale->brand || (int) $brandBriefForm->brand_id !== (int) $sale->brand_id) {
            abort(404, 'Brief form not found for this sale.');
        }

        $document = BriefFormSupport::resolveDocumentPath($brandBriefForm);

        if (! $document) {
            abort(404, 'Brief document not found for this brief form.');
        }

        return response()->download($document['path'], $document['filename']);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasRole(Role::ADMIN)) {
            abort(403);
        }

        $rules = $this->saleFormRules($request);
        $teamId = $user->team_id;
        $companyId = $user->company_id;

        if ($this->isProjectManager()) {
            // Requirement 6.3/6.4 — must select a Team from among their Joined_Teams.
            $rules['team_id'] = 'required|integer';
            $validatedTeam = $request->validate(['team_id' => $rules['team_id']]);

            if (! $user->approvedTeamIds()->contains((int) $validatedTeam['team_id'])) {
                abort(403, 'You can only create sales for teams you have joined.');
            }

            $teamId = (int) $validatedTeam['team_id'];
            $team = Team::find($teamId);
            $companyId = $team?->company_id;
        }

        $validated = $this->normalizeSaleInput($request->validate($rules));
        unset($validated['team_id']);

        $leadId = null;
        if (! empty($validated['lead_id'])) {
            $lead = \App\Models\Lead::query()->find((int) $validated['lead_id']);
            if ($lead && AuthScope::canAccessLead($lead)) {
                $leadId = $lead->id;
                if ($lead->status !== 'customer') {
                    $lead->update(['status' => 'customer']);
                }
            }
        }
        unset($validated['lead_id']);

        // Two ways to attach a client: pick an existing one from the dropdown (client_id),
        // or type a new client's details — in which case we save it as a new Client record too.
        $validated['client_id'] = $this->resolveOrCreateClient($validated, $teamId, $user->id);

        $sale = Sale::query()->create(array_merge($validated, [
            'user_id'         => $user->id,
            'team_id'         => $teamId,
            'company_id'      => $companyId,
            'lead_id'         => $leadId,
            'status'          => 'pending',
            'approval_status' => Sale::APPROVAL_PENDING,
            'is_draft'        => false,
        ]));

        if ($leadId && $sale->client_id) {
            \App\Models\Lead::whereKey($leadId)->whereNull('client_id')->update([
                'client_id' => $sale->client_id,
                'status' => 'customer',
            ]);
        }

        SaleNotificationDispatcher::dispatchSaleCreated($sale);

        ActivityLogger::log($user, UserActivityLog::TYPE_SALE_CREATED,
            "Created sale \"{$sale->title}\" (#{$sale->id})",
            ['sale_id' => $sale->id, 'amount' => $sale->amount]
        );

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale submitted and pending approval.');
    }

    public function show(Sale $sale): View
    {
        $this->authorizeView($sale);
        $sale->load(['user', 'team', 'company', 'brand', 'approvedBy', 'refundedBy', 'chargebackBy', 'invoices', 'accountManager', 'projectManager']);

        $canToggleRefund = $this->canToggleRefund($sale);
        $canToggleChargeback = $this->canToggleChargeback($sale);
        $canDecide = $this->canApprove($sale) && $sale->isPendingApproval(); // Approve / Reject buttons
        $canEdit = $this->userCanEditSale($sale);
        $canDelete = $this->userCanDeleteSale($sale);
        $invoiceService = app(InvoiceService::class);
        $sale = $invoiceService->syncReceivedAmount($sale);
        $invoices = $sale->invoices->sortByDesc('issued_at')->values();
        $billableRemaining = $invoiceService->billableRemaining($sale);
        $invoiceMaxAmount = $invoiceService->maxReceivableAmount($sale);
        // A Project Manager only records payments on their own sales (others' they just view)
        $canGenerateInvoice = $invoiceService->canGenerateForSale($sale)
            && (! $this->isProjectManager() || (int) $sale->user_id === (int) Auth::id());

        return view('admin.sales.show', compact(
            'sale',
            'canToggleRefund',
            'canToggleChargeback',
            'canDecide',
            'canEdit',
            'canDelete',
            'invoices',
            'billableRemaining',
            'invoiceMaxAmount',
            'canGenerateInvoice'
        ) + $this->briefShowViewData($sale));
    }

    public function edit(Sale $sale): View
    {
        $this->authorizeEdit($sale);

        return view('admin.sales.edit', [
            'sale'       => $sale,
            'brands'     => $this->brandsForDropdown(),
            'clients'    => \App\Http\Controllers\Admin\ClientController::forDropdown(),
            'staffUsers' => AuthScope::usersForDropdown(),
        ]);
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorizeEdit($sale);

        $validated = $this->normalizeSaleInput($request->validate($this->saleFormRules($request)));
        $validated['client_id'] = $this->resolveOrCreateClient($validated, $sale->team_id, Auth::id());

        // Only the sale form (edit) may change Sales Agent; create always uses the logged-in user.
        if (! array_key_exists('user_id', $request->all())) {
            unset($validated['user_id']);
        }

        if ($sale->is_refunded || $sale->is_chargeback || $sale->isApproved()) {
            unset($validated['status']);
            $sale->update($validated);

            return redirect()->route('admin.sales.index')
                ->with('success', 'Sale updated.');
        }

        $sale->update(array_merge($validated, [
            'status'          => 'pending',
            'approval_status' => Sale::APPROVAL_PENDING,
            'approval_note'   => null,
            'approved_by'     => null,
            'approved_at'     => null,
        ]));

        SaleNotificationDispatcher::dispatchSaleUpdated($sale, Auth::user());

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_SALE_UPDATED,
            "Updated sale \"{$sale->title}\" (#{$sale->id})",
            ['sale_id' => $sale->id, 'amount' => $sale->amount]
        );

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale updated and re-submitted for approval.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        abort_unless($this->userCanDeleteSale($sale), 403);

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_SALE_DELETED,
            "Deleted sale \"{$sale->title}\" (#{$sale->id})",
            ['sale_id' => $sale->id, 'amount' => $sale->amount]
        );

        $sale->delete();

        return redirect()->route('admin.sales.index')->with('success', 'Sale deleted.');
    }

    // ── Approval ─────────────────────────────────────────────────────────────────

    public function approve(Sale $sale): RedirectResponse
    {
        if (! $this->canApprove($sale)) abort(403);
        $this->applyApproval($sale);

        return back()->with('success', "Sale \"{$sale->title}\" approved.");
    }

    private function applyApproval(Sale $sale): void
    {
        $payload = [
            'approval_status' => Sale::APPROVAL_APPROVED,
            'approved_by'     => Auth::id(),
            'approved_at'     => now(),
            'approval_note'   => null,
        ];

        // Pending sales become completed on approval so targets/charts count them.
        if ($sale->status === 'pending') {
            $payload['status'] = 'completed';
        }

        $sale->update($payload);

        // Money typed as "Received amount" on the sale form gets its receipt now
        app(\App\Services\InvoiceService::class)->receiptForReceivedOnSale($sale, Auth::user());

        SaleNotificationDispatcher::dispatchSaleDecision($sale, Auth::user(), true);

        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_SALE_APPROVED,
            "Approved sale: {$sale->title} (\$" . number_format($sale->amount, 2) . ")"
        );
    }

    private function userCanBulkApprove(): bool
    {
        $user = Auth::user();
        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return true;
        }
        if (Team::headedBy($user->id)->exists()) {
            return true;
        }

        return \App\Models\SubTeamHead::where('user_id', $user->id)->exists();
    }

    public function reject(Request $request, Sale $sale): RedirectResponse
    {
        if (! $this->canApprove($sale) || ! $sale->isPendingApproval()) {
            abort(403);
        }

        $request->validate([
            'approval_note' => 'required|string|max:500',
        ]);

        $sale->update([
            'approval_status' => Sale::APPROVAL_REJECTED,
            'status'          => 'pending',
            'approved_by'     => Auth::id(),
            'approved_at'     => now(),
            'approval_note'   => $request->approval_note,
        ]);

        SaleNotificationDispatcher::dispatchSaleDecision($sale, Auth::user(), false);

        // Log activity
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_SALE_REJECTED,
            "Rejected sale: {$sale->title} (\$" . number_format($sale->amount, 2) . ") - Reason: {$request->approval_note}"
        );

        return back()->with('success', "Sale \"{$sale->title}\" rejected.");
    }

    public function toggleRefund(Request $request, Sale $sale): RedirectResponse
    {
        if (! $this->canToggleRefund($sale)) {
            abort(403);
        }

        $newRefunded = ! $sale->is_refunded;

        if ($newRefunded) {
            $maxRefund = round((float) $sale->amount, 2);
            $validated = $request->validate([
                'refunded_amount' => ['required', 'numeric', 'min:0.01', 'max:'.$maxRefund],
            ], [
                'refunded_amount.required' => 'Enter how much is being refunded.',
                'refunded_amount.max' => 'Refund cannot exceed the sale amount ($'.number_format($maxRefund, 2).').',
            ]);
            $refundedAmount = round((float) $validated['refunded_amount'], 2);

            $prev = $sale->status === Sale::STATUS_REFUNDED
                ? ($sale->status_before_refund ?? 'completed')
                : ($sale->status === Sale::STATUS_CHARGEBACK
                    ? ($sale->status_before_chargeback ?? 'completed')
                    : $sale->status);
            if (! in_array($prev, Sale::STATUSES, true)) {
                $prev = 'completed';
            }

            $sale->update([
                'is_refunded'          => true,
                'refunded_at'          => now(),
                'refunded_by'          => Auth::id(),
                'refunded_amount'      => $refundedAmount,
                'status_before_refund' => $prev,
                'status'               => Sale::STATUS_REFUNDED,
            ]);

            ActivityLogger::log(
                Auth::user(),
                UserActivityLog::TYPE_SALE_REFUNDED,
                "Marked sale as refunded: {$sale->title} (refund \$".number_format($refundedAmount, 2).' of $'.number_format((float) $sale->amount, 2).')'
            );
        } else {
            $restore = $sale->is_chargeback
                ? Sale::STATUS_CHARGEBACK
                : ($sale->status_before_refund ?? 'completed');
            $wasAmount = (float) ($sale->refunded_amount ?? $sale->amount);

            $sale->update([
                'is_refunded'          => false,
                'refunded_at'          => null,
                'refunded_by'          => null,
                'refunded_amount'      => null,
                'status'               => $restore,
                'status_before_refund' => null,
            ]);

            ActivityLogger::log(
                Auth::user(),
                UserActivityLog::TYPE_SALE_REFUND_REVERTED,
                "Reverted refund for sale: {$sale->title} (\$".number_format($wasAmount, 2).')'
            );
        }

        return redirect()
            ->back()
            ->with('success', $newRefunded
                ? 'Sale marked as refunded ($'.number_format((float) $sale->refunded_amount, 2).').'
                : 'Refund reverted.');
    }

    public function toggleChargeback(Request $request, Sale $sale): RedirectResponse
    {
        if (! $this->canToggleChargeback($sale)) {
            abort(403);
        }

        $newChargeback = ! $sale->is_chargeback;

        if ($newChargeback) {
            $maxChargeback = round((float) $sale->amount, 2);
            $validated = $request->validate([
                'chargeback_amount' => ['required', 'numeric', 'min:0.01', 'max:'.$maxChargeback],
            ], [
                'chargeback_amount.required' => 'Enter how much is being charged back.',
                'chargeback_amount.max' => 'Chargeback cannot exceed the sale amount ($'.number_format($maxChargeback, 2).').',
            ]);
            $chargebackAmount = round((float) $validated['chargeback_amount'], 2);

            $prev = $sale->status === Sale::STATUS_CHARGEBACK
                ? ($sale->status_before_chargeback ?? 'completed')
                : ($sale->status === Sale::STATUS_REFUNDED
                    ? ($sale->status_before_refund ?? 'completed')
                    : $sale->status);
            if (! in_array($prev, Sale::STATUSES, true)) {
                $prev = 'completed';
            }

            $sale->update([
                'is_chargeback' => true,
                'chargeback_at' => now(),
                'chargeback_by' => Auth::id(),
                'chargeback_amount' => $chargebackAmount,
                'status_before_chargeback' => $prev,
                'status' => Sale::STATUS_CHARGEBACK,
            ]);

            ActivityLogger::log(
                Auth::user(),
                UserActivityLog::TYPE_SALE_CHARGEBACK,
                "Marked sale as chargeback: {$sale->title} (chargeback \$".number_format($chargebackAmount, 2).' of $'.number_format((float) $sale->amount, 2).')'
            );
        } else {
            $restore = $sale->is_refunded
                ? Sale::STATUS_REFUNDED
                : ($sale->status_before_chargeback ?? 'completed');
            $wasAmount = (float) ($sale->chargeback_amount ?? $sale->amount);

            $sale->update([
                'is_chargeback' => false,
                'chargeback_at' => null,
                'chargeback_by' => null,
                'chargeback_amount' => null,
                'status' => $restore,
                'status_before_chargeback' => null,
            ]);

            ActivityLogger::log(
                Auth::user(),
                UserActivityLog::TYPE_SALE_CHARGEBACK_REVERTED,
                "Reverted chargeback for sale: {$sale->title} (\$".number_format($wasAmount, 2).')'
            );
        }

        return redirect()
            ->back()
            ->with('success', $newChargeback
                ? 'Sale marked as chargeback ($'.number_format((float) $sale->chargeback_amount, 2).').'
                : 'Chargeback reverted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function authorizeView(Sale $sale): void
    {
        $user = Auth::user();
        if (AuthScope::seesAllMoney()) return;
        if ($sale->user_id !== null && (int) $sale->user_id === (int) $user->id) return;

        // Unassigned imports on the viewer's team
        if ($sale->user_id === null && $sale->team_id && (int) $user->team_id === (int) $sale->team_id) {
            return;
        }

        // Requirement 6.5 — Project Manager: only sales from their approved (joined) teams
        if ($this->isProjectManager()) {
            if ($sale->team_id && $user->approvedTeamIdsWithSubTeams()->contains((int) $sale->team_id)) {
                return;
            }
            abort(403);
        }

        if ($sale->team_id && Team::headedBy($user->id)->where('id', $sale->team_id)->exists()) return;
        
        // Check if user is a sub-team head and the sale belongs to their sub-team member
        $subTeamHead = \App\Models\SubTeamHead::where('user_id', $user->id)->first();
        if ($subTeamHead) {
            $saleUser = \App\Models\User::find($sale->user_id);
            if ($saleUser && (int) $saleUser->sub_team_head_id === (int) $subTeamHead->id) {
                return;
            }
        }
        
        abort(403);
    }

    private function authorizeEdit(Sale $sale): void
    {
        if (! $this->userCanEditSale($sale)) {
            abort(403);
        }
    }

    private function userCanEditSale(Sale $sale): bool
    {
        $user = Auth::user();

        // Approved sales are locked — only Admin may edit (stops amount / agent changes after approval).
        if ($sale->isApproved()) {
            return $user->hasRole(Role::ADMIN);
        }

        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return true;
        }

        // Unassigned imports: team lead (or anyone on that team who can import) may assign an agent.
        if ($sale->user_id === null && $sale->team_id) {
            if (Team::headedBy($user->id)->where('id', $sale->team_id)->exists()) {
                return true;
            }
            if ((int) $user->team_id === (int) $sale->team_id && $user->hasRole([Role::AGENT, Role::PROJECT_MANAGER])) {
                return true;
            }
        }

        if ((int) $sale->user_id !== (int) $user->id) {
            return false;
        }

        return $sale->isEditableByOwner();
    }

    /** Delete while not approved; Admin may delete anytime. */
    private function userCanDeleteSale(Sale $sale): bool
    {
        $user = Auth::user();
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
        if ($sale->isApproved()) {
            return false;
        }

        return $this->userCanEditSale($sale) || $this->canApprove($sale);
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'pending'   => 'warning',
            'cancelled' => 'danger',
            Sale::STATUS_REFUNDED => 'dark',
            Sale::STATUS_CHARGEBACK => 'dark',
            default     => 'secondary',
        };
    }

    private static function approvalColor(string $status): string
    {
        return match ($status) {
            Sale::APPROVAL_APPROVED => 'success',
            Sale::APPROVAL_REJECTED => 'danger',
            default                 => 'warning',
        };
    }

    private static function approvalLabel(string $status): string
    {
        return match ($status) {
            Sale::APPROVAL_APPROVED => 'Approved',
            Sale::APPROVAL_REJECTED => 'Rejected',
            default                 => 'Pending',
        };
    }

    private static function saleTypeColor(string $type): string
    {
        return match ($type) {
            Sale::TYPE_UPSELL => 'info',
            default           => 'primary',
        };
    }

    /** @return array<string, mixed> */
    private function briefShowViewData(Sale $sale): array
    {
        if ($sale->is_draft || ! $sale->brand) {
            return ['briefForms' => collect()];
        }

        $sale->brand->loadMissing(['briefForms.briefFormType']);

        $submissions = BriefSubmission::query()
            ->where('sale_id', $sale->id)
            ->with('brandBriefForm')
            ->get();

        $submissionsByFormId = $submissions
            ->filter(fn (BriefSubmission $s) => $s->brand_brief_form_id !== null)
            ->keyBy('brand_brief_form_id');
        $submissionsByBriefType = $submissions->keyBy('brief_type');

        $schemaService = app(BriefFormSchemaService::class);

        $briefForms = BriefFormSupport::activeFormsForBrand($sale->brand)
            ->map(function (BrandBriefForm $form) use ($sale, $submissionsByFormId, $submissionsByBriefType, $schemaService) {
                $typeSlug = $form->resolveSlug();
                $submission = $submissionsByFormId->get($form->id)
                    ?? $submissionsByBriefType->get($typeSlug);

                $fieldLabels = $form->hasSchema()
                    ? $form->fieldLabels()
                    : ($submission?->brandBriefForm?->hasSchema()
                        ? $submission->brandBriefForm->fieldLabels()
                        : BriefSubmissionLabels::forType($typeSlug));

                if ($submission !== null && is_array($submission->meta['field_labels'] ?? null) && $submission->meta['field_labels'] !== []) {
                    $fieldLabels = array_merge($fieldLabels, $submission->meta['field_labels']);
                }

                $orderedFieldIds = $form->hasSchema()
                    ? $schemaService->fieldIdsFromSchema($form->schema)
                    : ($submission?->brandBriefForm?->hasSchema()
                        ? $schemaService->fieldIdsFromSchema($submission->brandBriefForm->schema)
                        : null);

                if ($submission !== null && is_array($submission->data)) {
                    $orderedFieldIds = array_values(array_unique(array_merge(
                        $orderedFieldIds ?? [],
                        array_keys($submission->data)
                    )));
                }

                $schema = $form->hasSchema()
                    ? $form->schema
                    : ($submission?->brandBriefForm?->hasSchema()
                        ? $submission->brandBriefForm->schema
                        : null);

                $submissionData = is_array($submission?->data) ? $submission->data : [];

                return [
                    'name'             => $form->name,
                    'url'              => BriefFormSupport::briefFormUrl($form, $sale->id),
                    'downloadUrl'      => $form->hasDocument()
                        ? route('admin.sales.brief-document', [$sale, $form])
                        : null,
                    'briefType'        => $typeSlug,
                    'submissionStatus' => $submission?->isSubmitted() ? 'submitted' : 'pending',
                    'submittedAt'      => $submission?->submitted_at,
                    'submission'       => $submission,
                    'fieldLabels'      => $fieldLabels,
                    'orderedFieldIds'  => $orderedFieldIds,
                    'schema'           => $schema,
                    'displayBlocks'    => $submission !== null
                        ? BriefSubmissionDisplay::displayBlocks($schema, $submissionData, $fieldLabels)
                        : [],
                ];
            });

        return compact('briefForms');
    }

    private function briefTypeSlugFromForm(BrandBriefForm $form): string
    {
        $slug = strtolower($form->form_path);
        $slug = trim($slug, '/');
        $slug = preg_replace('/-brief$/', '', $slug) ?: 'website';

        return match (true) {
            str_contains($slug, 'logo')   => 'logo',
            str_contains($slug, 'ebook')  => 'ebook',
            str_contains($slug, 'website'), str_contains($slug, 'web') => 'website',
            default => $slug,
        };
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Brand>|\Illuminate\Support\Collection<int, Brand> */
    private function brandsForDropdown()
    {
        return AuthScope::brandsForDropdown();
    }

    /** @param array<string, mixed> $validated */
    private function normalizeSaleInput(array $validated): array
    {
        $validated['received_amount'] = $validated['received_amount'] ?? 0;
        $validated['is_ppc'] = ! empty($validated['is_ppc']);
        $validated['account_manager_id'] = $validated['account_manager_id'] ?? null;
        $validated['project_manager_id'] = $validated['project_manager_id'] ?? null;
        if (array_key_exists('user_id', $validated) && ($validated['user_id'] === '' || $validated['user_id'] === null)) {
            $validated['user_id'] = null;
        }
        $validated['merchant'] = isset($validated['merchant']) && trim((string) $validated['merchant']) !== ''
            ? trim((string) $validated['merchant'])
            : null;
        $validated['service_category'] = ! empty($validated['service_category'])
            ? $validated['service_category']
            : null;

        return $validated;
    }

    /**
     * Two ways to attach a client to a sale:
     * 1. An existing client was picked from the dropdown (client_id already set) — reuse it,
     *    but keep it up to date if the agent edited the name/email/phone on the sale form.
     * 2. No client_id was picked — a brand new client typed on the sale form is saved as a
     *    new Client record (scoped to the sale's team) and linked automatically.
     *
     * @param array<string, mixed> $validated
     */
    private function resolveOrCreateClient(array $validated, ?int $teamId, int $creatorId): ?int
    {
        $clientId = $validated['client_id'] ?? null;

        if ($clientId) {
            $allowedIds = \App\Http\Controllers\Admin\ClientController::accessibleClientIds()
                ->map(fn ($id) => (int) $id);
            if (! $allowedIds->contains((int) $clientId)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'client_id' => 'You cannot attach this client to the sale.',
                ]);
            }

            $client = \App\Models\Client::find($clientId);
            if ($client) {
                $updates = array_filter([
                    'name'  => $validated['client_name'] ?? $client->name,
                    'email' => $validated['client_email'] ?? $client->email,
                    'phone' => $validated['client_phone'] ?? $client->phone,
                ], fn ($v) => $v !== null && $v !== '');

                if (! $client->brand_id && ! empty($validated['brand_id'])) {
                    $updates['brand_id'] = $validated['brand_id'];
                }

                if ($updates !== []) {
                    $client->update($updates);
                }

                return $client->id;
            }
        }

        if (empty($validated['client_name'])) {
            return null;
        }

        // No existing client selected — try to match an existing client in the same team
        // by name (case-insensitive) to avoid creating duplicates, otherwise create new.
        $existing = \App\Models\Client::query()
            ->where('team_id', $teamId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['client_name'])])
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $client = \App\Models\Client::query()->create([
            'name'       => $validated['client_name'],
            'email'      => $validated['client_email'] ?? null,
            'phone'      => $validated['client_phone'] ?? null,
            'brand_id'   => $validated['brand_id'] ?? null,
            'team_id'    => $teamId,
            'created_by' => $creatorId,
            'is_active'  => true,
            'currency'   => 'USD',
        ]);
        \App\Support\ClientTrelloLink::linkLater($client);

        return $client->id;
    }

    /** @return array<string, mixed> */
    private function saleFormRules(Request $request): array
    {
        return [
            'title'            => 'required|string|max:255',
            'brand_id'         => AuthScope::brandIdRule(required: true),
            'client_id'        => [
                'nullable',
                \Illuminate\Validation\Rule::in(
                    \App\Http\Controllers\Admin\ClientController::accessibleClientIds()
                        ->flatMap(fn ($id) => [(int) $id, (string) $id])
                        ->unique()
                        ->values()
                        ->all()
                ),
            ],
            'lead_id'          => 'nullable|exists:leads,id',
            'client_name'      => 'required|string|max:255',
            'client_email'     => 'nullable|email|max:255',
            'client_phone'     => 'nullable|string|max:50',
            'amount'           => 'required|numeric|min:0',
            'received_amount'  => [
                'nullable',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value !== null && (float) $value > (float) $request->input('amount', 0)) {
                        $fail('Received amount cannot exceed total amount.');
                    }
                },
            ],
            'sale_date'        => 'required|date',
            'sale_type'        => 'required|in:' . implode(',', Sale::SALE_TYPES),
            'service_category' => 'nullable|in:' . implode(',', Sale::SERVICE_CATEGORIES),
            'merchant'         => 'nullable|string|max:120',
            'is_ppc'           => 'sometimes|boolean',
            'user_id'          => 'nullable|integer|exists:users,id',
            'account_manager_id' => 'nullable|integer|exists:users,id',
            'project_manager_id' => 'nullable|integer|exists:users,id',
            'notes'            => 'nullable|string',
        ];
    }

    /** Default Front/Upsell from the agent's sub-team seat title. */
    private function suggestedSaleType(\App\Models\User $user): string
    {
        $user->loadMissing('subTeamHead');
        $title = strtolower((string) ($user->subTeamHead?->title ?? ''));

        if ($title === '') {
            $asHead = \App\Models\SubTeamHead::where('user_id', $user->id)->value('title');
            $title = strtolower((string) ($asHead ?? ''));
        }

        if (str_contains($title, 'upsell')) {
            return Sale::TYPE_UPSELL;
        }

        return Sale::TYPE_FRONT;
    }
}
