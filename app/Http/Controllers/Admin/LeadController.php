<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
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

        return view('admin.leads.index', compact('teams'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = Lead::query()
            ->with(['assignedUser', 'team'])
            ->select('leads.*');

        AuthScope::scopeLeads($query);

        // Filters
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::eloquent($query)
            ->addColumn('assigned_to_name', fn (Lead $lead) => e($lead->assignedUser?->name ?? '-'))
            ->addColumn('team_name', fn (Lead $lead) => e($lead->team?->name ?? '-'))
            ->editColumn('status', fn (Lead $lead) => '<span class="badge bg-' . self::statusColor($lead->status) . '">' . ucfirst($lead->status) . '</span>')
            ->editColumn('source', fn (Lead $lead) => ucwords(str_replace('_', ' ', $lead->source ?? '-')))
            ->addColumn('actions', function (Lead $lead) {
                $showUrl   = route('admin.leads.show', $lead);
                $editUrl   = route('admin.leads.edit', $lead);
                $deleteUrl = route('admin.leads.destroy', $lead);
                $csrf      = csrf_field();
                $method    = method_field('DELETE');
                $isAgent   = auth()->user()?->hasRole('Agent');

                $html = '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a>
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>';
                if (!$isAgent) {
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
        $users = AuthScope::usersForDropdown();
        $teams = AuthScope::teamsForDropdown();

        return view('admin.leads.create', compact('users', 'teams'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'source'      => 'nullable|string|max:100',
            'status'      => 'required|in:' . implode(',', Lead::STATUSES),
            'assigned_to' => 'nullable|exists:users,id',
            'team_id'     => 'nullable|exists:teams,id',
            'notes'       => 'nullable|string',
        ]);

        // Auto-assign team for agents
        if (AuthScope::isAgent()) {
            $validated['team_id'] = auth()->user()->team_id;
        }

        Lead::query()->create($validated);

        return redirect()->route('admin.leads.index')->with('success', 'Lead created successfully.');
    }

    public function show(Lead $lead): View
    {
        $lead->load(['assignedUser', 'team']);

        return view('admin.leads.show', compact('lead'));
    }

    public function edit(Lead $lead): View
    {
        $users = AuthScope::usersForDropdown();
        $teams = AuthScope::teamsForDropdown();

        return view('admin.leads.edit', compact('lead', 'users', 'teams'));
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'source'      => 'nullable|string|max:100',
            'status'      => 'required|in:' . implode(',', Lead::STATUSES),
            'assigned_to' => 'nullable|exists:users,id',
            'team_id'     => 'nullable|exists:teams,id',
            'notes'       => 'nullable|string',
        ]);

        $lead->update($validated);

        return redirect()->route('admin.leads.index')->with('success', 'Lead updated successfully.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        return redirect()->route('admin.leads.index')->with('success', 'Lead deleted successfully.');
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'new'       => 'primary',
            'contacted' => 'info',
            'proposal'  => 'warning',
            'won'       => 'success',
            'lost'      => 'danger',
            default     => 'secondary',
        };
    }
}
