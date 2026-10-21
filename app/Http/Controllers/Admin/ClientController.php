<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SubTeamHead;
use App\Models\Team;
use App\Models\User;
use App\Services\TrelloClientDispatcher;
use App\Support\AuthScope;
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

        if (AuthScope::seesAllMoney()) { // Admin, Manager, Finance
            return null; // no restriction
        }

        if ($this->isProjectManager()) {
            return $user->approvedTeamIdsWithSubTeams();
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
        // An agent works their own clients only (like leads): not every client of the team
        if (AuthScope::seesOnlyOwnClients()) {
            AuthScope::scopeOwnClients($query);
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
        $base = $this->baseQuery();
        $stats = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
            'contacts' => \App\Models\ClientContact::query()
                ->whereIn('client_id', (clone $base)->select('clients.id'))
                ->count(),
        ];

        return view('admin.clients.index', compact('stats'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->with('brand')->select('clients.*');

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        $query->withCount(['sales as sales_count' => fn ($q) => $q->where('is_draft', false)]);

        return DataTables::eloquent($query)
            ->addColumn('brand_name', fn (Client $client) => e($client->brand?->name ?? '-'))
            ->addColumn('active_badge', function (Client $client) {
                $class = $client->is_active ? 'success' : 'secondary';
                $label = $client->is_active ? 'Active' : 'Inactive';

                return '<span class="badge bg-' . $class . '">' . $label . '</span>';
            })
            ->addColumn('team_name', fn (Client $client) => e($client->team?->name ?? '-'))
            ->addColumn('created_by_name', fn (Client $client) => e($client->createdBy?->name ?? '-'))
            ->addColumn('sales_count', fn (Client $client) => (int) $client->sales_count)
            ->addColumn('actions', function (Client $client) {
                $showUrl = route('admin.clients.show', $client);
                $editUrl = route('admin.clients.edit', $client);
                $deleteUrl = route('admin.clients.destroy', $client);
                $csrf = csrf_field();
                $method = method_field('DELETE');

                $html = '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a>';
                // Finance only looks at clients (to bill them)
                if (AuthScope::isFinance()) {
                    return $html;
                }
                $html .= ' <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>';

                if (Auth::user()->hasRole([Role::ADMIN, Role::MANAGER])) {
                    $html .= '
                    <form action="'.$deleteUrl.'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this client?">
                        '.$csrf.$method.'
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>';
                }

                return $html;
            })
            ->rawColumns(['actions', 'active_badge'])
            ->toJson();
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        $brands = AuthScope::brandsForDropdown();
        $teams = $this->teamsForClientForm();

        return view('admin.clients.create', compact('brands', 'teams'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->clientRules());
        $payload = $this->clientPayload($validated, $request);
        $teamId = $this->resolveTeamIdForWrite($validated);

        if (! $teamId) {
            return back()->withInput()->with('error', 'A team is required for this client.');
        }

        $client = Client::query()->create(array_merge($payload, [
            'team_id'    => $teamId,
            'created_by' => Auth::id(),
            'currency'   => $payload['currency'] ?? 'USD',
        ]));

        $this->syncPrimaryContact($client, $validated);
        TrelloClientDispatcher::dispatch($client->fresh());
        \App\Support\ClientTrelloLink::linkLater($client);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Client added successfully.');
    }

    /** All Trello projects (cards) of the client, for the client page (JSON, loaded after the page). */
    public function trello(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClient($client);
        $client->loadMissing('team.parentTeam', 'team.trelloIntegration');
        $team = \App\Support\ClientTrelloLink::trelloTeamFor($client->team);
        if (! $team) {
            return response()->json(['ok' => false, 'message' => 'Trello is not connected for this client\'s team.'], 404);
        }
        if ($request->hasSession()) {
            $request->session()->save(); // don't hold the session lock while waiting on Trello
        }

        try {
            $index = \App\Http\Controllers\Admin\ProductionDashboardController::overviewData($team, $request->boolean('refresh'))['card_index'] ?? [];
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $now = now();
        $canOpen = Auth::user()->hasRole([Role::ADMIN, Role::MANAGER, Role::AGENT, Role::PROJECT_MANAGER, Role::PRODUCTION]);
        $cards = [];
        foreach (\App\Support\ClientTrelloLink::cardsFor($client, $index) as $cardId => $c) {
            $cards[] = [
                'name' => $c['name'],
                'board' => $c['board'],
                'list' => $c['list'],
                'due' => $c['due'],
                'done' => $c['done'],
                'late' => ! $c['done'] && $c['due'] && \Illuminate\Support\Carbon::parse($c['due'])->lt($now),
                'url' => $canOpen ? route('admin.trello.board', ['boardId' => $c['board_id'], 'team' => $team->id, 'card' => $cardId]) : null,
            ];
        }
        // Open first (late, then nearest due, then no due date), completed last
        $rank = fn ($c) => $c['done'] ? 3 : ($c['late'] ? 0 : ($c['due'] ? 1 : 2));
        usort($cards, fn ($a, $b) => [$rank($a), (string) $a['due'], $a['board']] <=> [$rank($b), (string) $b['due'], $b['board']]);

        return response()->json(['ok' => true, 'cards' => $cards]);
    }

    public function show(Client $client): View
    {
        $this->authorizeClient($client);

        $client->load(['team', 'createdBy', 'brand', 'contacts']);

        $salesQuery = $client->sales()
            ->where('is_draft', false)
            ->with(['user', 'team', 'brand']);
        $this->scopeSalesForViewer($salesQuery);
        $sales = $salesQuery->latest('sale_date')->get();

        $totalSaleAmount = $sales->sum('amount');
        $totalReceived   = $sales->sum('received_amount');
        $totalRemaining  = max(0, $totalSaleAmount - $totalReceived);

        $visibleSaleIds = $sales->pluck('id');
        $invoices = Invoice::query()
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->where(function ($q) use ($client, $visibleSaleIds) {
                if ($visibleSaleIds->isNotEmpty()) {
                    $q->whereIn('sale_id', $visibleSaleIds);
                } else {
                    $q->whereRaw('1 = 0');
                }

                $q->orWhere(function ($standalone) use ($client) {
                    $standalone->where('client_id', $client->id)->whereNull('sale_id');
                    $this->scopeStandaloneInvoicesForViewer($standalone);
                });
            })
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

        $brands = AuthScope::brandsForDropdown();
        $teams = $this->teamsForClientForm();

        return view('admin.clients.edit', compact('client', 'brands', 'teams'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClient($client);

        $validated = $request->validate($this->clientRules());
        $payload = $this->clientPayload($validated, $request);

        if (array_key_exists('team_id', $validated) || Auth::user()->hasRole([Role::ADMIN, Role::MANAGER]) || $this->isTeamHead()) {
            $teamId = $this->resolveTeamIdForWrite($validated);
            if ($teamId) {
                $payload['team_id'] = $teamId;
            }
        }

        $client->update($payload);
        $this->syncPrimaryContact($client, $validated);

        TrelloClientDispatcher::dispatch($client->fresh());
        \App\Support\ClientTrelloLink::linkLater($client); // e.g. name corrected to match the card

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        if (! Auth::user()->hasRole([Role::ADMIN, Role::MANAGER])) {
            abort(403);
        }

        $saleCount = $client->sales()->count();
        $invoiceCount = Invoice::query()
            ->where(function ($q) use ($client) {
                $q->where('client_id', $client->id)
                    ->orWhereIn('sale_id', $client->sales()->select('id'));
            })
            ->count();
        $leadCount = \App\Models\Lead::query()->where('client_id', $client->id)->count();

        if ($saleCount > 0 || $invoiceCount > 0 || $leadCount > 0) {
            return back()->with(
                'error',
                'Cannot delete this client — linked records exist'
                . " (sales: {$saleCount}, invoices: {$invoiceCount}, leads: {$leadCount})."
            );
        }

        $client->contacts()->delete();
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
        return (new self())->baseQuery()->orderBy('name')->get(['id', 'name', 'email', 'phone', 'team_id', 'brand_id']);
    }

    /** Client IDs the current user may attach to sales/invoices. */
    public static function accessibleClientIds(): \Illuminate\Support\Collection
    {
        return (new self())->baseQuery()->pluck('id');
    }

    private function clientRules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'brand_id' => AuthScope::brandIdRule(),
            'notes' => 'nullable|string',
            'contact_first_name' => 'nullable|string|max:100',
            'contact_last_name' => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ];

        if (Auth::user()->hasRole([Role::ADMIN, Role::MANAGER]) || $this->isTeamHead()) {
            $rules['team_id'] = 'required|exists:teams,id';
        }

        return $rules;
    }

    private function clientPayload(array $validated, Request $request): array
    {
        unset(
            $validated['contact_first_name'],
            $validated['contact_last_name'],
            $validated['contact_email'],
            $validated['contact_phone'],
            $validated['contact_position']
        );

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['brand_id'] = ($validated['brand_id'] ?? null) ?: null;
        if (! array_key_exists('currency', $validated) || ! $validated['currency']) {
            $validated['currency'] = 'USD';
        }

        return $validated;
    }

    private function syncPrimaryContact(Client $client, array $validated): void
    {
        $first = trim((string) ($validated['contact_first_name'] ?? ''));
        if ($first === '') {
            return;
        }

        $client->contacts()->updateOrCreate(
            ['is_primary' => true],
            [
                'first_name' => $first,
                'last_name' => $validated['contact_last_name'] ?? null,
                'email' => $validated['contact_email'] ?? null,
                'phone' => $validated['contact_phone'] ?? null,
                'position' => $validated['contact_position'] ?? null,
                'is_primary' => true,
            ]
        );

        if (! $client->email && ! empty($validated['contact_email'])) {
            $client->update(['email' => $validated['contact_email']]);
        }
        if (! $client->phone && ! empty($validated['contact_phone'])) {
            $client->update(['phone' => $validated['contact_phone']]);
        }
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
        if (AuthScope::seesOnlyOwnClients() && ! AuthScope::scopeOwnClients(Client::query()->whereKey($client->id))->exists()) {
            abort(403);
        }
    }

    private function teamsForClientForm()
    {
        if (Auth::user()->hasRole([Role::ADMIN, Role::MANAGER])) {
            return Team::query()->sales()->orderBy('name')->get(['id', 'name']);
        }

        if ($this->isTeamHead()) {
            return Team::query()
                ->where('team_head_id', Auth::id())
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return collect();
    }

    private function resolveTeamIdForWrite(array $validated): ?int
    {
        if (! empty($validated['team_id'])) {
            $teamId = (int) $validated['team_id'];
            $allowed = $this->visibleTeamIds();
            if ($allowed === null || $allowed->contains($teamId)) {
                return $teamId;
            }
            abort(403, 'You cannot assign this team.');
        }

        return $this->defaultTeamIdForCreate();
    }

    /** Limit client-show sales to what the viewer may normally see on Sales. */
    private function scopeSalesForViewer($query): void
    {
        $user = Auth::user();

        if (AuthScope::seesAllMoney()) {
            return;
        }

        if ($this->isProjectManager()) {
            $query->whereIn('team_id', $user->approvedTeamIds());

            return;
        }

        if ($this->isTeamHead()) {
            $query->whereIn('team_id', Team::where('team_head_id', $user->id)->pluck('id'));

            return;
        }

        $subTeamHeadIds = SubTeamHead::where('user_id', $user->id)->pluck('id');
        if ($subTeamHeadIds->isNotEmpty()) {
            $memberIds = User::whereIn('sub_team_head_id', $subTeamHeadIds)
                ->pluck('id')
                ->push($user->id)
                ->unique()
                ->values();
            $query->whereIn('user_id', $memberIds);

            return;
        }

        $query->where('user_id', $user->id);
    }

    private function scopeStandaloneInvoicesForViewer($query): void
    {
        $user = Auth::user();

        if (AuthScope::seesAllMoney() || $this->isTeamHead() || $this->isProjectManager()) {
            return;
        }

        $query->where('created_by', $user->id);
    }
}
