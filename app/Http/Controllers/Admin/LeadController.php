<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Role;
use App\Services\TrelloClientDispatcher;
use App\Support\AuthScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class LeadController extends Controller
{
    public function index(): View
    {
        $teams = AuthScope::teamsForDropdown();
        $countQuery = Lead::query();
        AuthScope::scopeLeads($countQuery);
        $counts = $countQuery->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.leads.index', compact('teams', 'counts'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = Lead::query()
            ->with(['assignedUser', 'team', 'brand'])
            ->select('leads.*');

        AuthScope::scopeLeads($query);

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('overdue_follow_up')) {
            $query->whereNotNull('follow_up_at')
                ->whereDate('follow_up_at', '<', today())
                ->whereNotIn('status', ['customer', 'lost']);
        }

        return DataTables::eloquent($query)
            ->addColumn('assigned_to_name', fn (Lead $lead) => e($lead->assignedUser?->name ?? '-'))
            ->addColumn('team_name', fn (Lead $lead) => e($lead->team?->name ?? '-'))
            ->addColumn('brand_name', fn (Lead $lead) => e($lead->brand?->name ?? '-'))
            ->editColumn('company', fn (Lead $lead) => e($lead->company ?: '-'))
            ->editColumn('lead_value', fn (Lead $lead) => '$' . number_format((float) $lead->lead_value, 2))
            ->editColumn('status', function (Lead $lead) {
                $html = '<span class="badge bg-' . self::statusColor($lead->status) . '">' . e($lead->statusLabel()) . '</span>';
                if ($lead->isFollowUpOverdue()) {
                    $html .= ' <span class="badge bg-danger">Follow-up overdue</span>';
                } elseif ($lead->follow_up_at) {
                    $html .= ' <span class="badge bg-light text-dark border">' . e($lead->follow_up_at->format('d M')) . '</span>';
                }

                return $html;
            })
            ->editColumn('source', fn (Lead $lead) => ucwords(str_replace('_', ' ', $lead->source ?? '-')))
            ->addColumn('actions', function (Lead $lead) {
                $showUrl   = route('admin.leads.show', $lead);
                $editUrl   = route('admin.leads.edit', $lead);
                $deleteUrl = route('admin.leads.destroy', $lead);
                $csrf      = csrf_field();
                $method    = method_field('DELETE');
                $canDelete = AuthScope::isAdminOrManager();

                $html = '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a>
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>';
                if ($canDelete) {
                    $html .= '
                    <form action="'.$deleteUrl.'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this lead?">
                        '.$csrf.$method.'
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>';
                }
                return $html;
            })
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        // An agent can only assign leads to themselves
        $users = AuthScope::seesOnlyOwnLeads() ? collect([auth()->user()]) : AuthScope::usersForDropdown();
        $teams = AuthScope::teamsForDropdown();
        $brands = AuthScope::brandsForDropdown();

        return view('admin.leads.create', compact('users', 'teams', 'brands'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->leadRules());
        $validated = $this->leadPayload($validated);

        if (! AuthScope::isAdminOrManager()) {
            $validated['team_id'] = auth()->user()->team_id;
            $validated['assigned_to'] = $validated['assigned_to'] ?: auth()->id();
        }
        if (AuthScope::seesOnlyOwnLeads()) {
            $validated['assigned_to'] = auth()->id(); // otherwise the agent would lose the lead they just made
        }
        if (! $this->canAssignTo($validated['assigned_to'] ?? null)) {
            return back()->withInput()->withErrors(['assigned_to' => 'You can only assign leads to members of your team.']);
        }
        if ($message = $this->duplicateMessage($validated)) {
            return back()->withInput()->withErrors(['duplicate' => $message]);
        }

        Lead::query()->create($validated);

        return redirect()->route('admin.leads.index')->with('success', 'Lead created successfully.');
    }

    public function show(Lead $lead): View
    {
        $this->authorizeLead($lead);
        $lead->load(['assignedUser', 'team', 'brand', 'client', 'activities.user']);

        return view('admin.leads.show', compact('lead'));
    }

    public function storeActivity(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($lead);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'type' => 'nullable|in:note,follow_up,contact',
            'follow_up_at' => 'nullable|date',
        ]);

        LeadActivity::query()->create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => $validated['type'] ?? LeadActivity::TYPE_NOTE,
            'body' => $validated['body'],
        ]);

        $leadUpdates = [];
        if (! empty($validated['follow_up_at'])) {
            $leadUpdates['follow_up_at'] = $validated['follow_up_at'];
        }
        if (($validated['type'] ?? '') === LeadActivity::TYPE_CONTACT) {
            $leadUpdates['last_contact_at'] = now()->toDateString();
        }
        if ($leadUpdates !== []) {
            $lead->update($leadUpdates);
        }

        return back()->with('success', 'Activity added.');
    }

    public function edit(Lead $lead): View
    {
        $this->authorizeLead($lead);
        // An agent can only assign leads to themselves
        $users = AuthScope::seesOnlyOwnLeads() ? collect([auth()->user()]) : AuthScope::usersForDropdown();
        $teams = AuthScope::teamsForDropdown();
        $brands = AuthScope::brandsForDropdown();

        return view('admin.leads.edit', compact('lead', 'users', 'teams', 'brands'));
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($lead);
        $validated = $this->leadPayload($request->validate($this->leadRules()));

        if (! AuthScope::isAdminOrManager()) {
            $validated['team_id'] = $lead->team_id ?: auth()->user()->team_id;
        }
        if (AuthScope::seesOnlyOwnLeads()) {
            $validated['assigned_to'] = auth()->id(); // agents can't hand their lead to someone else
        }
        if (! $this->canAssignTo($validated['assigned_to'] ?? null, $lead)) {
            return back()->withInput()->withErrors(['assigned_to' => 'You can only assign leads to members of your team.']);
        }
        if ($message = $this->duplicateMessage($validated, $lead->id)) {
            return back()->withInput()->withErrors(['duplicate' => $message]);
        }

        $lead->update($validated);

        return redirect()->route('admin.leads.index')->with('success', 'Lead updated successfully.');
    }

    public function convert(Lead $lead): RedirectResponse
    {
        $this->authorizeLead($lead);

        $user = auth()->user();
        $isAdmin = $user->hasRole(Role::ADMIN);

        if ($lead->client_id) {
            if ($isAdmin) {
                return redirect()
                    ->route('admin.clients.show', $lead->client_id)
                    ->with('success', 'Client already exists for this lead.');
            }

            return redirect()->route('admin.sales.create', [
                'lead_id' => $lead->id,
                'client_id' => $lead->client_id,
            ])->with('success', 'Client already exists — create a sale from this lead.');
        }

        $teamId = $lead->team_id ?: $user->team_id;
        if (! $teamId && AuthScope::isAdminOrManager()) {
            $teamId = \App\Models\Team::query()->sales()->orderBy('id')->value('id');
        }

        if (! $teamId) {
            return back()->with('error', 'Cannot convert lead without a team. Assign a team on the lead first.');
        }

        $client = Client::query()->create([
            'name' => $lead->company ?: $lead->name,
            'company_name' => $lead->company,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'website' => $lead->website,
            'address' => $lead->address,
            'city' => $lead->city,
            'state' => $lead->state,
            'zip' => $lead->zip,
            'country' => $lead->country,
            'brand_id' => $lead->brand_id,
            'notes' => $lead->notes,
            'team_id' => $teamId,
            'created_by' => $user->id,
            'is_active' => true,
            'currency' => 'USD',
        ]);

        if ($lead->name) {
            $client->contacts()->create([
                'first_name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'position' => $lead->position,
                'is_primary' => true,
            ]);
        }

        $lead->update([
            'status' => 'customer',
            'client_id' => $client->id,
            'team_id' => $lead->team_id ?: $teamId,
        ]);

        TrelloClientDispatcher::dispatch($client->fresh());
        \App\Support\ClientTrelloLink::linkLater($client);

        if ($isAdmin) {
            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Lead converted to client. Admins cannot create sales — assign an agent to create the sale.');
        }

        return redirect()->route('admin.sales.create', [
            'lead_id' => $lead->id,
            'client_id' => $client->id,
        ])->with('success', 'Lead converted to client. Complete the sale below.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->authorizeLead($lead);

        if (! AuthScope::isAdminOrManager()) {
            abort(403);
        }

        $lead->delete();

        return redirect()->route('admin.leads.index')->with('success', 'Lead deleted successfully.');
    }

    /**
     * Assignee must be someone the user may pick in the form (their team's sellers); the form
     * only lists those, this stops a hand-made request from handing a lead outside the team.
     * Keeping the lead's current assignee is always fine.
     */
    private function canAssignTo(mixed $userId, ?Lead $lead = null): bool
    {
        if (! $userId || AuthScope::isAdminOrManager() || (int) $userId === (int) auth()->id()) {
            return true;
        }
        if ($lead && (int) $lead->assigned_to === (int) $userId) {
            return true;
        }

        return AuthScope::usersForDropdown()->contains('id', (int) $userId);
    }

    /**
     * One person = one lead: block a lead whose email or phone is already on another lead (any
     * team, any agent), so two agents don't chase the same client. A name alone isn't checked —
     * different people share names. Agents / PMs aren't told whose lead it is.
     */
    private function duplicateMessage(array $data, ?int $ignoreId = null): ?string
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $phone = self::phoneKey($data['phone'] ?? null);
        if ($email === '' && $phone === null) {
            return null;
        }

        $match = null;
        $field = null;
        if ($email !== '') {
            $match = Lead::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->first();
            $field = $match ? 'email' : null;
        }
        if (! $match && $phone !== null) {
            // Phones are stored in any format ("+1 (555) 010-2030"), so compare digits in PHP
            Lead::query()->whereNotNull('phone')->where('phone', '!=', '')
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->select(['id', 'name', 'phone', 'assigned_to'])
                ->lazyById(500)
                ->each(function (Lead $lead) use ($phone, &$match) {
                    if (self::phoneKey($lead->phone) === $phone) {
                        $match = $lead;

                        return false;
                    }
                });
            $field = $match ? 'phone' : null;
        }
        if (! $match) {
            return null;
        }

        $match->loadMissing('assignedUser:id,name');
        if ((int) $match->assigned_to === (int) auth()->id()) {
            return "You already have a lead with this {$field}: {$match->name}. Open that lead instead of adding a new one.";
        }
        if (AuthScope::seesOnlyOwnLeads()) {
            return "A lead with this {$field} already exists and belongs to another team member. Ask your team lead if this client should be yours.";
        }

        return "A lead with this {$field} already exists: {$match->name} (assigned to ".($match->assignedUser?->name ?? 'nobody').'). Reassign that lead instead of adding a new one.';
    }

    /** Last 10 digits of a phone number (drops +country code / spaces / dashes); null if too short. */
    private static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 7 ? substr($digits, -10) : null;
    }

    private function authorizeLead(Lead $lead): void
    {
        if (! AuthScope::canAccessLead($lead)) {
            abort(403);
        }
    }

    private function leadRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'lead_value' => 'nullable|numeric|min:0',
            'brand_id' => AuthScope::brandIdRule(),
            'source' => 'nullable|string|max:100',
            'status' => 'required|in:' . implode(',', Lead::STATUSES),
            'assigned_to' => 'nullable|exists:users,id',
            'team_id' => 'nullable|exists:teams,id',
            'follow_up_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }

    private function leadPayload(array $validated): array
    {
        // Fields can be missing from the request (e.g. Team is only shown to Admin / Manager)
        $validated['brand_id'] = ($validated['brand_id'] ?? null) ?: null;
        $validated['assigned_to'] = ($validated['assigned_to'] ?? null) ?: null;
        $validated['team_id'] = ($validated['team_id'] ?? null) ?: null;
        $validated['follow_up_at'] = ($validated['follow_up_at'] ?? null) ?: null;
        $validated['lead_value'] = $validated['lead_value'] ?? 0;
        // Simplified form no longer edits these — do not wipe existing values on update.
        unset($validated['position'], $validated['website'], $validated['tags'], $validated['address'], $validated['city'], $validated['state'], $validated['zip'], $validated['country'], $validated['is_public']);

        return $validated;
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'new_lead' => 'primary',
            'contacted', 'follow_up' => 'info',
            'proposal' => 'warning',
            'customer' => 'success',
            'lost' => 'danger',
            default => 'secondary',
        };
    }
}
