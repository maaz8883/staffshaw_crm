<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Team;
use App\Services\SaleNotificationDispatcher;
use App\Support\AuthScope;
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
        $query = Sale::query()->with(['user', 'team', 'company'])->select('sales.*');

        if ($user->hasRole('Admin')) {
            // all
        } elseif ($this->isTeamHead()) {
            $teamIds = Team::where('team_head_id', $user->id)->pluck('id');
            $query->whereIn('team_id', $teamIds);
        } else {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function canApprove(Sale $sale): bool
    {
        $user = Auth::user();
        if ($user->hasRole('Admin')) return true;
        if ($sale->team_id && Team::where('team_head_id', $user->id)->where('id', $sale->team_id)->exists()) return true;
        return false;
    }

    // ── Index ────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $user    = Auth::user();
        $isAgent = $this->isAgent();

        $teams            = AuthScope::teamsForDropdown();
        $users            = $isAgent ? collect() : AuthScope::usersForDropdown();
        $pendingCount     = $this->baseQuery()->where('approval_status', Sale::APPROVAL_PENDING)->count();

        return view('admin.sales.index', compact('teams', 'users', 'isAgent', 'pendingCount'));
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

        return DataTables::eloquent($query)
            ->editColumn('amount', fn (Sale $s) => '$' . number_format($s->amount, 2))
            ->editColumn('sale_date', fn (Sale $s) => $s->sale_date->format('d M Y'))
            ->editColumn('status', fn (Sale $s) =>
                '<span class="badge bg-' . self::statusColor($s->status) . '">' . ucfirst($s->status) . '</span>')
            ->addColumn('approval_badge', fn (Sale $s) =>
                '<span class="badge bg-' . self::approvalColor($s->approval_status) . '">' . self::approvalLabel($s->approval_status) . '</span>')
            ->addColumn('agent_name', fn (Sale $s) => e($s->user?->name ?? '-'))
            ->addColumn('team_name', fn (Sale $s) => e($s->team?->name ?? '-'))
            ->addColumn('actions', function (Sale $sale) {
                $user      = Auth::user();
                $canEdit   = ($user->hasRole('Admin') || $sale->user_id === $user->id)
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
            ->rawColumns(['status', 'approval_badge', 'actions'])
            ->toJson();
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.sales.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'amount'      => 'required|numeric|min:0',
            'sale_date'   => 'required|date',
            'status'      => 'required|in:' . implode(',', Sale::STATUSES),
            'notes'       => 'nullable|string',
        ]);

        $user = Auth::user();

        $sale = Sale::query()->create(array_merge($validated, [
            'user_id'         => $user->id,
            'team_id'         => $user->team_id,
            'company_id'      => $user->company_id,
            'approval_status' => Sale::APPROVAL_PENDING,
        ]));

        SaleNotificationDispatcher::dispatchSaleCreated($sale);

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale submitted and pending approval.');
    }

    public function show(Sale $sale): View
    {
        $this->authorizeView($sale);
        $sale->load(['user', 'team', 'company', 'approvedBy']);

        return view('admin.sales.show', compact('sale'));
    }

    public function edit(Sale $sale): View
    {
        $this->authorizeEdit($sale);

        return view('admin.sales.edit', compact('sale'));
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorizeEdit($sale);

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'amount'      => 'required|numeric|min:0',
            'sale_date'   => 'required|date',
            'status'      => 'required|in:' . implode(',', Sale::STATUSES),
            'notes'       => 'nullable|string',
        ]);

        // Re-submit for approval on edit
        $sale->update(array_merge($validated, [
            'approval_status' => Sale::APPROVAL_PENDING,
            'approval_note'   => null,
            'approved_by'     => null,
            'approved_at'     => null,
        ]));

        SaleNotificationDispatcher::dispatchSaleUpdated($sale, $user);

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale updated and re-submitted for approval.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        if (! Auth::user()->hasRole('Admin')) abort(403);

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

        return back()->with('success', "Sale \"{$sale->title}\" rejected.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function authorizeView(Sale $sale): void
    {
        $user = Auth::user();
        if ($user->hasRole('Admin')) return;
        if ($sale->user_id === $user->id) return;
        if ($sale->team_id && Team::where('team_head_id', $user->id)->where('id', $sale->team_id)->exists()) return;
        abort(403);
    }

    private function authorizeEdit(Sale $sale): void
    {
        $user = Auth::user();
        if ($user->hasRole('Admin')) return;
        if ($sale->user_id === $user->id && $sale->isPendingApproval()) return;
        abort(403);
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'pending'   => 'warning',
            'cancelled' => 'danger',
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
}
