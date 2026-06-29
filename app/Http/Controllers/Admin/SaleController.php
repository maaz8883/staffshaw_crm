<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandBriefForm;
use App\Services\OrbitBriefSubmissionClient;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use App\Services\SaleNotificationDispatcher;
use App\Services\TrelloSaleDispatcher;
use App\Support\AuthScope;
use App\Support\BriefFormSupport;
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
        return Team::where('team_head_id', Auth::id())->exists();
    }

    private function isAgent(): bool
    {
        return Auth::user()->hasRole('Agent') && ! $this->isTeamHead();
    }

    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user  = Auth::user();
        $query = Sale::query()->with(['user', 'team', 'company', 'brand'])->select('sales.*')
            ->where('is_draft', false);

        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            // Admin and Manager: all sales
        } elseif ($this->isTeamHead()) {
            // Team Head: sales from their teams
            $teamIds = Team::where('team_head_id', $user->id)->pluck('id');
            $query->whereIn('team_id', $teamIds);
        } else {
            // Check if user is a sub-team head
            $subTeamHead = \App\Models\SubTeamHead::where('user_id', $user->id)->first();
            
            if ($subTeamHead) {
                // Sub-Team Head: their own sales + their sub-team members' sales
                $subTeamMemberIds = \App\Models\User::where('sub_team_head_id', $subTeamHead->id)
                    ->pluck('id')
                    ->toArray();
                
                // Include the sub-team head themselves
                $subTeamMemberIds[] = $user->id;
                
                $query->whereIn('user_id', $subTeamMemberIds);
            } else {
                // Regular Agent: only their own sales
                $query->where('user_id', $user->id);
            }
        }

        return $query;
    }

    private function canApprove(Sale $sale): bool
    {
        $user = Auth::user();
        if ($user->hasRole('Admin')) return true;
        if ($sale->team_id && Team::where('team_head_id', $user->id)->where('id', $sale->team_id)->exists()) return true;
        
        // Check if user is a sub-team head and the sale belongs to their sub-team member
        $subTeamHead = \App\Models\SubTeamHead::where('user_id', $user->id)->first();
        if ($subTeamHead) {
            $saleUser = \App\Models\User::find($sale->user_id);
            if (
                (int) $sale->user_id === (int) $user->id
                || ($saleUser && (int) $saleUser->sub_team_head_id === (int) $subTeamHead->id)
            ) {
                return true;
            }
        }
        
        return false;
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

        return view('admin.sales.index', compact('teams', 'users', 'brands', 'isAgent', 'pendingCount'));
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
        if ($request->filled('refunded')) {
            if ($request->refunded === '1') {
                $query->where('is_refunded', true);
            } elseif ($request->refunded === '0') {
                $query->where('is_refunded', false);
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

        return DataTables::eloquent($query)
            ->editColumn('amount', function (Sale $s) {
                $fmt = '$' . number_format($s->amount, 2);
                if ($s->is_refunded) {
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
            ->addColumn('refund_toggle', function (Sale $sale) {
                if (! $this->canApprove($sale)) {
                    return $sale->is_refunded
                        ? '<span class="badge bg-danger">Refunded</span>'
                        : '<span class="text-muted small">—</span>';
                }

                $url     = route('admin.sales.toggle-refund', $sale);
                $checked = $sale->is_refunded ? 'checked' : '';
                $csrf    = csrf_token();

                return '<form action="' . $url . '" method="POST" class="d-inline js-refund-toggle-form" data-sale-id="' . $sale->id . '">'
                    . '<input type="hidden" name="_token" value="' . $csrf . '">'
                    . '<div class="form-check form-switch mb-0 d-flex justify-content-center">'
                    . '<input class="form-check-input" type="checkbox" role="switch" ' . $checked
                    . ' onchange="this.form.submit()" title="Mark refunded / revert" aria-label="Refunded">'
                    . '</div></form>';
            })
            ->editColumn('status', fn (Sale $s) =>
                '<span class="badge bg-' . self::statusColor($s->status) . '">' . e($s->statusLabel()) . '</span>')
            ->addColumn('approval_badge', fn (Sale $s) =>
                '<span class="badge bg-' . self::approvalColor($s->approval_status) . '">' . self::approvalLabel($s->approval_status) . '</span>')
            ->addColumn('agent_name', fn (Sale $s) => e($s->user?->name ?? '-'))
            ->addColumn('team_name', fn (Sale $s) => e($s->team?->name ?? '-'))
            ->addColumn('brand_name', fn (Sale $s) => e($s->brand?->name ?? '-'))
            ->addColumn('actions', function (Sale $sale) {
                $user      = Auth::user();
                $canEdit   = ($user->hasRole('Admin') || (int) $sale->user_id === (int) $user->id)
                             && $sale->approval_status === Sale::APPROVAL_PENDING;
                $canDelete = $user->hasRole('Admin');
                $canApprove = $this->canApprove($sale) && $sale->isPendingApproval();
                $canReject  = $this->canApprove($sale) && $sale->approval_status !== Sale::APPROVAL_REJECTED;

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
            ->rawColumns(['amount', 'remaining_amount', 'sale_type_badge', 'refund_toggle', 'status', 'approval_badge', 'actions'])
            ->toJson();
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        if (Auth::user()->hasRole(Role::ADMIN)) {
            abort(403);
        }

        return view('admin.sales.create', ['brands' => $this->brandsForDropdown()]);
    }

    public function downloadBriefDocument(Sale $sale, BrandBriefForm $brandBriefForm)
    {
        $this->authorizeView($sale);

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
        if (Auth::user()->hasRole(Role::ADMIN)) {
            abort(403);
        }

        $validated = $this->normalizeSaleInput($request->validate($this->saleFormRules($request)));

        $user = Auth::user();

        $sale = Sale::query()->create(array_merge($validated, [
            'user_id'         => $user->id,
            'team_id'         => $user->team_id,
            'company_id'      => $user->company_id,
            'status'          => 'completed',
            'approval_status' => Sale::APPROVAL_PENDING,
            'is_draft'        => false,
        ]));

        SaleNotificationDispatcher::dispatchSaleCreated($sale);
        $trelloResult = TrelloSaleDispatcher::dispatch($sale);

        ActivityLogger::log($user, UserActivityLog::TYPE_SALE_CREATED,
            "Created sale \"{$sale->title}\" (#{$sale->id})",
            ['sale_id' => $sale->id, 'amount' => $sale->amount]
        );

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale submitted and pending approval.')
            ->with('trello_diagnostics', $trelloResult);
    }

    public function show(Sale $sale): View
    {
        $this->authorizeView($sale);
        $sale->load(['user', 'team', 'company', 'brand', 'approvedBy', 'refundedBy']);

        $canToggleRefund = $this->canApprove($sale);

        return view('admin.sales.show', compact('sale', 'canToggleRefund') + $this->briefShowViewData($sale));
    }

    public function edit(Sale $sale): View
    {
        $this->authorizeEdit($sale);

        return view('admin.sales.edit', [
            'sale'   => $sale,
            'brands' => $this->brandsForDropdown(),
        ]);
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorizeEdit($sale);

        $validated = $this->normalizeSaleInput($request->validate($this->saleFormRules($request)));

        if ($sale->is_refunded) {
            unset($validated['status']);
            $sale->update($validated);

            return redirect()->route('admin.sales.index')
                ->with('success', 'Sale updated.');
        }

        $sale->update(array_merge($validated, [
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
        if (! Auth::user()->hasRole('Admin')) abort(403);

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

        $sale->update([
            'approval_status' => Sale::APPROVAL_APPROVED,
            'approved_by'     => Auth::id(),
            'approved_at'     => now(),
            'approval_note'   => null,
        ]);

        SaleNotificationDispatcher::dispatchSaleDecision($sale, Auth::user(), true);

        // Log activity
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_SALE_APPROVED,
            "Approved sale: {$sale->title} (\$" . number_format($sale->amount, 2) . ")"
        );

        return back()->with('success', "Sale \"{$sale->title}\" approved.");
    }

    public function reject(Request $request, Sale $sale): RedirectResponse
    {
        if (! $this->canApprove($sale)) abort(403);

        $request->validate([
            'approval_note' => 'required|string|max:500',
        ]);

        $sale->update([
            'approval_status' => Sale::APPROVAL_REJECTED,
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

    public function toggleRefund(Sale $sale): RedirectResponse
    {
        if (! $this->canApprove($sale)) {
            abort(403);
        }

        $newRefunded = ! $sale->is_refunded;

        if ($newRefunded) {
            $prev = $sale->status === Sale::STATUS_REFUNDED
                ? ($sale->status_before_refund ?? 'completed')
                : $sale->status;
            if (! in_array($prev, Sale::STATUSES, true)) {
                $prev = 'completed';
            }

            $sale->update([
                'is_refunded'          => true,
                'refunded_at'          => now(),
                'refunded_by'          => Auth::id(),
                'status_before_refund' => $prev,
                'status'               => Sale::STATUS_REFUNDED,
            ]);

            // Log refund activity
            ActivityLogger::log(
                Auth::user(),
                UserActivityLog::TYPE_SALE_REFUNDED,
                "Marked sale as refunded: {$sale->title} (\$" . number_format($sale->amount, 2) . ")"
            );
        } else {
            $sale->update([
                'is_refunded'          => false,
                'refunded_at'          => null,
                'refunded_by'          => null,
                'status'               => $sale->status_before_refund ?? 'completed',
                'status_before_refund' => null,
            ]);

            // Log refund revert activity
            ActivityLogger::log(
                Auth::user(),
                UserActivityLog::TYPE_SALE_REFUND_REVERTED,
                "Reverted refund for sale: {$sale->title} (\$" . number_format($sale->amount, 2) . ")"
            );
        }

        return redirect()
            ->back()
            ->with('success', $newRefunded ? 'Sale marked as refunded.' : 'Refund reverted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function authorizeView(Sale $sale): void
    {
        $user = Auth::user();
        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) return;
        if ((int) $sale->user_id === (int) $user->id) return;
        if ($sale->team_id && Team::where('team_head_id', $user->id)->where('id', $sale->team_id)->exists()) return;
        
        // Check if user is a sub-team head and the sale belongs to their sub-team member
        $subTeamHead = \App\Models\SubTeamHead::where('user_id', $user->id)->first();
        if ($subTeamHead) {
            $saleUser = \App\Models\User::find($sale->user_id);
            if ($saleUser && $saleUser->sub_team_head_id === $subTeamHead->id) {
                return;
            }
        }
        
        abort(403);
    }

    private function authorizeEdit(Sale $sale): void
    {
        $user = Auth::user();
        if ($user->hasRole('Admin')) return;
        if ((int) $sale->user_id === (int) $user->id && $sale->isPendingApproval()) return;
        abort(403);
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'pending'   => 'warning',
            'cancelled' => 'danger',
            Sale::STATUS_REFUNDED => 'dark',
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

        $submissions = app(OrbitBriefSubmissionClient::class)->forSale($sale->id);

        $briefForms = BriefFormSupport::activeFormsForBrand($sale->brand)
            ->map(function (BrandBriefForm $form) use ($sale, $submissions) {
                $typeSlug = $form->briefFormType?->slug ?? $this->briefTypeSlugFromForm($form);
                $submission = $submissions->get($typeSlug);

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
                    'fieldLabels'      => BriefSubmissionLabels::forType($typeSlug),
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

    /** @return \Illuminate\Database\Eloquent\Collection<int, Brand> */
    private function brandsForDropdown()
    {
        return Brand::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @param array<string, mixed> $validated */
    private function normalizeSaleInput(array $validated): array
    {
        $validated['received_amount'] = $validated['received_amount'] ?? 0;

        return $validated;
    }

    /** @return array<string, mixed> */
    private function saleFormRules(Request $request): array
    {
        return [
            'title'            => 'required|string|max:255',
            'brand_id'         => 'required|exists:brands,id',
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
            'notes'            => 'nullable|string',
        ];
    }
}
