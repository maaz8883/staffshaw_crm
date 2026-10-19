<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SubTeamHead;
use App\Models\Team;
use App\Models\User;
use App\Support\AuthScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $user = Auth::user();
        if (! $user || $user->hasRole([Role::PPC, Role::PRODUCTION, Role::IT, Role::FINANCE, Role::HR])) {
            return response()->json(['results' => []]);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $results = collect();

        foreach ([
            'searchUsers',
            'searchClients',
            'searchLeads',
            'searchSales',
            'searchInvoices',
        ] as $method) {
            try {
                $results = $results->merge($this->{$method}($like));
            } catch (\Throwable) {
                // One module failing must not blank the whole search.
            }
        }

        return response()->json([
            'results' => $results->take(20)->values(),
        ]);
    }

    private function searchUsers(string $like): array
    {
        $viewer = Auth::user();
        $query = User::query()
            ->accountActive()
            ->with(['role', 'team:id,name,team_head_id'])
            ->select(['id', 'name', 'email', 'role_id', 'team_id']);

        AuthScope::scopeUsers($query);

        return $query
            ->where(function (Builder $q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(function (User $u) use ($viewer) {
                $isHead = $u->team && (int) $u->team->team_head_id === (int) $u->id;

                return [
                    'type' => 'User',
                    'icon' => 'person-badge',
                    'title' => $u->name,
                    'subtitle' => collect([
                        $isHead ? 'Team Lead' : ($u->role?->name),
                        $u->email,
                        $u->team?->name,
                    ])->filter()->implode(' · '),
                    'url' => $this->userResultUrl($u, $viewer),
                ];
            })
            ->all();
    }

    private function userResultUrl(User $found, User $viewer): string
    {
        if ($viewer->hasRole(Role::ADMIN)) {
            return route('admin.users.show', $found);
        }

        if ((int) $viewer->id === (int) $found->id) {
            return route('admin.profile.show');
        }

        if ($found->team_id && AuthScope::canAccessTeam((int) $found->team_id)) {
            return route('admin.teams.show', $found->team_id);
        }

        return route('admin.sales.index');
    }

    private function searchClients(string $like): array
    {
        $query = Client::query()->select(['id', 'name', 'email', 'phone', 'company_name', 'team_id']);
        $this->scopeByVisibleTeams($query, 'team_id');

        return $query
            ->where(function (Builder $q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('company_name', 'like', $like);
            })
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Client $c) => [
                'type' => 'Client',
                'icon' => 'people',
                'title' => $c->name,
                'subtitle' => collect([$c->company_name, $c->email, $c->phone])->filter()->implode(' · '),
                'url' => route('admin.clients.show', $c),
            ])
            ->all();
    }

    private function searchLeads(string $like): array
    {
        $query = Lead::query()->select(['id', 'name', 'email', 'phone', 'company', 'status']);
        AuthScope::scopeLeads($query);

        return $query
            ->where(function (Builder $q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('company', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Lead $lead) => [
                'type' => 'Lead',
                'icon' => 'funnel',
                'title' => $lead->name,
                'subtitle' => collect([
                    Lead::STATUS_LABELS[$lead->status] ?? $lead->status,
                    $lead->company,
                    $lead->email,
                ])->filter()->implode(' · '),
                'url' => route('admin.leads.show', $lead),
            ])
            ->all();
    }

    private function searchSales(string $like): array
    {
        $query = $this->salesBaseQuery()
            ->select(['id', 'title', 'client_name', 'client_email', 'amount', 'status', 'user_id']);

        return $query
            ->with('user:id,name')
            ->where(function (Builder $q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('client_name', 'like', $like)
                    ->orWhere('client_email', 'like', $like)
                    ->orWhere('client_phone', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like));
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Sale $sale) => [
                'type' => 'Sale',
                'icon' => 'cash-stack',
                'title' => $sale->title,
                'subtitle' => collect([
                    $sale->client_name,
                    $sale->user?->name ? ('Agent: ' . $sale->user->name) : null,
                    '$' . number_format((float) $sale->amount, 0),
                    ucfirst((string) $sale->status),
                ])->filter()->implode(' · '),
                'url' => route('admin.sales.show', $sale),
            ])
            ->all();
    }

    private function searchInvoices(string $like): array
    {
        $query = Invoice::query()
            ->select(['id', 'invoice_number', 'status', 'amount', 'sale_id', 'client_id', 'client_name'])
            ->with(['client:id,name']);

        // Mirror sales visibility for linked sale invoices; standalone by creator / team.
        if (! AuthScope::isAdminOrManager()) {
            $saleIds = $this->salesBaseQuery()->pluck('id');
            $user = Auth::user();
            $query->where(function (Builder $q) use ($saleIds, $user) {
                $q->whereIn('sale_id', $saleIds->isEmpty() ? [0] : $saleIds);
                if ($user->hasRole(Role::PROJECT_MANAGER) || AuthScope::headedTeamIds()->isNotEmpty()) {
                    // team heads / PMs already covered via sale ids
                } else {
                    $q->orWhere(function (Builder $standalone) use ($user) {
                        $standalone->whereNull('sale_id')->where('created_by', $user->id);
                    });
                }
            });
        }

        return $query
            ->where(function (Builder $q) use ($like) {
                $q->where('invoice_number', 'like', $like)
                    ->orWhere('client_name', 'like', $like)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like));
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'type' => 'Invoice',
                'icon' => 'receipt',
                'title' => $invoice->invoice_number ?: ('Invoice #' . $invoice->id),
                'subtitle' => collect([
                    $invoice->client?->name ?: $invoice->client_name,
                    '$' . number_format((float) $invoice->amount, 0),
                    ucfirst((string) $invoice->status),
                ])->filter()->implode(' · '),
                'url' => route('admin.invoices.show', $invoice),
            ])
            ->all();
    }

    private function salesBaseQuery(): Builder
    {
        $user = Auth::user();
        $query = Sale::query()->where('is_draft', false);

        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return $query;
        }

        if ($user->hasRole(Role::PROJECT_MANAGER)) {
            $ids = $user->approvedTeamIds();

            return $query->whereIn('team_id', $ids->isEmpty() ? [0] : $ids);
        }

        $headed = Team::where('team_head_id', $user->id)->pluck('id');
        if ($headed->isNotEmpty()) {
            return $query->whereIn('team_id', $headed);
        }

        $subTeamHeadIds = SubTeamHead::where('user_id', $user->id)->pluck('id');
        if ($subTeamHeadIds->isNotEmpty()) {
            $memberIds = User::whereIn('sub_team_head_id', $subTeamHeadIds)
                ->pluck('id')
                ->push($user->id)
                ->unique()
                ->values();

            return $query->whereIn('user_id', $memberIds);
        }

        return $query->where('user_id', $user->id);
    }

    /** @param  Builder<\Illuminate\Database\Eloquent\Model>  $query */
    private function scopeByVisibleTeams(Builder $query, string $column = 'team_id'): void
    {
        $user = Auth::user();

        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return;
        }

        if ($user->hasRole(Role::PROJECT_MANAGER)) {
            $ids = $user->approvedTeamIds();
            $query->whereIn($column, $ids->isEmpty() ? [0] : $ids);

            return;
        }

        $headed = Team::where('team_head_id', $user->id)->pluck('id');
        if ($headed->isNotEmpty()) {
            $query->whereIn($column, $headed);

            return;
        }

        $sub = SubTeamHead::where('user_id', $user->id)->first();
        if ($sub) {
            $query->where($column, $sub->team_id);

            return;
        }

        $query->where($column, $user->team_id ?: 0);
    }
}
