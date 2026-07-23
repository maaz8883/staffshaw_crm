<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SubTeamHead;
use App\Models\Team;
use App\Models\User;
use App\Services\TrelloClientDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ClientController extends Controller
{
    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function isTeamHead(): bool
    {
        return Team::where('team_head_id', Auth::id())->exists();
    }

    private function isProjectManager(): bool
    {
        return Auth::user()->hasRole(Role::PROJECT_MANAGER);
    }

    /** Team IDs the current user is allowed to see clients for (null = no team scope applies to them, empty = admin/manager sees all). */
    private function visibleTeamIds(): ?\Illuminate\Support\Collection
    {
        $user = Auth::user();

        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return null; // no restriction
        }

        if ($this->isProjectManager()) {
            return $user->approvedTeamIds();
        }

        if ($this->isTeamHead()) {
            return Team::where('team_head_id', $user->id)->pluck('id');
        }

        $subTeamHead = SubTeamHead::where('user_id', $user->id)->first();
        if ($subTeamHead) {
            return collect([$subTeamHead->team_id]);
        }

        // Regular Agent / Sub-Team Head member: their own team only.
        return $user->team_id ? collect([$user->team_id]) : collect();
    }

    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Client::query()->with(['team', 'createdBy']);

        $teamIds = $this->visibleTeamIds();
        if ($teamIds !== null) {
            $query->whereIn('team_id', $teamIds);
        }

        return $query;
    }

    /** Team a newly created client (or sale) should be attached to, for the current user. */
    private function defaultTeamIdForCreate(): ?int
    {
        $user = Auth::user();

        if ($this->isTeamHead()) {
            return Team::where('team_head_id', $user->id)->value('id');
        }

        $subTeamHead = SubTeamHead::where('user_id', $user->id)->first();
        if ($subTeamHead) {
            return $subTeamHead->team_id;
        }

        return $user->team_id;
    }

    // ── Index / Datatable ────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.clients.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->select('clients.*');

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        return DataTables::eloquent($query)
            ->addColumn('team_name', fn (Client $client) => e($client->team?->name ?? '-'))
            ->addColumn('created_by_name', fn (Client $client) => e($client->createdBy?->name ?? '-'))
            ->addColumn('sales_count', fn (Client $client) => $client->sales()->where('is_draft', false)->count())
            ->addColumn('actions', function (Client $client) {
                $showUrl = route('admin.clients.show', $client);
                $editUrl = route('admin.clients.edit', $client);
                $deleteUrl = route('admin.clients.destroy', $client);
                $csrf = csrf_field();
                $method = method_field('DELETE');

                $html = '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a>
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>';

                if (Auth::user()->hasRole([Role::ADMIN, Role::MANAGER])) {
                    $html .= '
                    <form action="'.$deleteUrl.'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this client?">
                        '.$csrf.$method.'
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>';
                }

                return $html;
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'address'      => 'nullable|string',
            'notes'        => 'nullable|string',
        ]);

        $client = Client::query()->create(array_merge($validated, [
            'team_id'    => $this->defaultTeamIdForCreate(),
            'created_by' => Auth::id(),
        ]));

        TrelloClientDispatcher::dispatch($client);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Client added successfully.');
    }

    public function show(Client $client): View
    {
        $this->authorizeClient($client);

        $client->load(['team', 'createdBy']);

        $sales = $client->sales()
            ->where('is_draft', false)
            ->with(['user', 'team', 'brand'])
            ->latest('sale_date')
            ->get();

        $totalSaleAmount = $sales->sum('amount');
        $totalReceived   = $sales->sum('received_amount');
        $totalRemaining  = max(0, $totalSaleAmount - $totalReceived);

        $invoices = Invoice::query()
            ->whereIn('sale_id', $sales->pluck('id'))
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->latest('issued_at')
            ->get();

        $totalInvoiced = $invoices->sum('amount');

        return view('admin.clients.show', compact(
            'client', 'sales', 'totalSaleAmount', 'totalReceived', 'totalRemaining',
            'invoices', 'totalInvoiced'
        ));
    }

    public function edit(Client $client): View
    {
        $this->authorizeClient($client);

        return view('admin.clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClient($client);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'address'      => 'nullable|string',
            'notes'        => 'nullable|string',
        ]);

        $client->update($validated);

        TrelloClientDispatcher::dispatch($client->fresh());

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        if (! Auth::user()->hasRole([Role::ADMIN, Role::MANAGER])) {
            abort(403);
        }

        $client->delete();

        return redirect()->route('admin.clients.index')->with('success', 'Client deleted successfully.');
    }

    /** AJAX endpoint used by the sale form's client dropdown to fetch a client's details. */
    public function lookup(Client $client): JsonResponse
    {
        $this->authorizeClient($client);

        return response()->json([
            'id'    => $client->id,
            'name'  => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
        ]);
    }

    /** For dropdowns — clients scoped to the same teams the current user can see. */
    public static function forDropdown()
    {
        return (new self())->baseQuery()->orderBy('name')->get(['id', 'name', 'email', 'phone', 'team_id']);
    }

    private function authorizeClient(Client $client): void
    {
        $teamIds = $this->visibleTeamIds();

        if ($teamIds === null) {
            return; // Admin / Manager
        }

        if (! $teamIds->contains($client->team_id)) {
            abort(403);
        }
    }
}
