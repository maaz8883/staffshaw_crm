@extends('admin.layout')

@push('styles')
<style>
    .dashboard-chart-card { border-radius: 12px; overflow: hidden; }
    .dashboard-chart-wrap { position: relative; width: 100%; }
    .dash-more-toggle {
        display: flex; align-items: center; justify-content: space-between;
        width: 100%; margin: 4px 0 16px; padding: 10px 14px;
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px;
        color: var(--text-muted); font-size: 13px; font-weight: 600; cursor: pointer;
    }
    .dash-more-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
    #dash-more[hidden], #dash-more tr[hidden] { display: none !important; }
</style>
@endpush

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-icon', 'speedometer2')

@section('content')
@php
    $isAgent    = auth()->user()->hasRole('Agent') && !isset($isTeamHeadView) && !isset($isSubTeamHeadView);
    $isTeamHead = isset($isTeamHeadView) && $isTeamHeadView;
    $isSubTeamHead = isset($isSubTeamHeadView) && $isSubTeamHeadView;
    $isProjectManagerDash = isset($isProjectManagerView) && $isProjectManagerView;
@endphp

@include('admin.partials.crm-overview')

@if($isProjectManagerDash)
{{-- ═══════════════════════════════════════════════════════════════
     PROJECT MANAGER DASHBOARD
═══════════════════════════════════════════════════════════════ --}}

@if(! $hasJoinedTeams)
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-diagram-3 fs-1 text-muted"></i>
        <h5 class="mt-3">No teams joined yet</h5>
        <p class="text-muted">Browse the Team Directory and request to join a team. Once a Team Head approves your request, that team's data will show up here.</p>
        <a href="{{ route('admin.team-memberships.index') }}" class="btn btn-primary">Browse Teams</a>
    </div>
</div>
@else

@if(isset($pmTrelloTeams) && $pmTrelloTeams->isNotEmpty())
    @include('admin.partials.pm-trello')
@endif

{{-- Joined team cards with members --}}
@forelse($teamDashboardCards as $card)
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ $card['name'] }}</strong>
            <span class="text-muted ms-2 small">{{ $card['company_name'] }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary">{{ $card['members_count'] }} members</span>
            @if($card['achievement_percent'] !== null)
                <span class="badge bg-success">{{ $card['achievement_percent'] }}% achieved</span>
            @endif
        </div>
    </div>
    <div class="card-body">
        @if($card['target'] > 0)
        <div class="mb-3">
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">{{ $card['month_label'] }} — Team Target</span>
                <span class="fw-semibold">${{ number_format($card['monthly_revenue'], 0) }} / ${{ number_format($card['target'], 0) }}</span>
            </div>
            <div class="progress rounded-pill" style="height:10px">
                <div class="progress-bar rounded-pill" style="width:{{ min(100, $card['achievement_percent'] ?? 0) }}%"></div>
            </div>
        </div>
        @endif
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Member</th>
                        <th>Role</th>
                        <th class="text-center">Sales</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">{{ $card['month_label'] }} Target</th>
                        <th class="text-center">Achieve</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($card['members'] as $member)
                    <tr>
                        <td>
                            {{ $member['name'] }}
                            @if($member['is_head'])
                                <span class="badge bg-warning text-dark ms-1" style="font-size:10px">Head</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $member['role_name'] }}</td>
                        <td class="text-center">{{ $member['sales_count'] }}</td>
                        <td class="text-end text-success">${{ number_format($member['sale_amount'], 0) }}</td>
                        <td class="text-end">${{ number_format($member['month_target'], 0) }}</td>
                        <td class="text-center">
                            @if($member['target_achievement_pct'] !== null)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">{{ $member['target_achievement_pct'] }}%</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted text-center py-3">No members.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@empty
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-4 text-muted">No data available for your joined teams yet.</div>
</div>
@endforelse

{{-- My recent sales --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-cash-stack text-primary"></i> My Recent Sales</span>
        <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Title</th><th>Client</th><th>Team</th><th>Status</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    @forelse($myRecentSales as $sale)
                    @php $sc2 = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger']; @endphp
                    <tr>
                        <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                        <td class="text-muted small">{{ $sale->client_name }}</td>
                        <td class="text-muted small">{{ $sale->team?->name }}</td>
                        <td><span class="badge bg-{{ $sc2[$sale->status] ?? 'secondary' }}">{{ ucfirst($sale->status) }}</span></td>
                        <td class="text-end fw-semibold">${{ number_format($sale->amount, 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted text-center py-3">No sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endif

@elseif($isSubTeamHead)
{{-- ═══════════════════════════════════════════════════════════════
     SUB-TEAM HEAD DASHBOARD
═══════════════════════════════════════════════════════════════ --}}

@php
    $tta = $teamTargetAchievement ?? ['target' => $teamTargetAmt ?? 0, 'achieved' => 0, 'percent' => null];
@endphp
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small"><i class="bi bi-flag"></i> Team target</div>
                        <div class="fw-semibold">{{ $currentMonthLabel }} · {{ $team?->name ?? 'Team not assigned' }}</div>
                    </div>
                    @if(($tta['percent'] ?? null) !== null)
                        <span class="badge rounded-pill bg-primary px-3 py-2">{{ $tta['percent'] }}%</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Achieved (this month)</span>
                    <span class="fw-semibold">${{ number_format($tta['achieved'] ?? 0, 0) }} <span class="text-muted fw-normal">/ ${{ number_format($tta['target'] ?? $teamTargetAmt, 0) }}</span></span>
                </div>
                <div class="progress rounded-pill" style="height:12px">
                    <div class="progress-bar bg-primary rounded-pill" style="width:{{ min(100, $tta['percent'] ?? 0) }}%"></div>
                </div>
                @if(($tta['target'] ?? $teamTargetAmt) <= 0)
                    <p class="text-muted small mb-0 mt-2">No team target set for this month.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small"><i class="bi bi-flag-fill"></i> Sub-team target</div>
                        <div class="fw-semibold">{{ $currentMonthLabel }} · {{ $subTeamHead->title }}</div>
                    </div>
                    @if($subTeamTargetAchievement['percent'] !== null)
                        <span class="badge rounded-pill bg-success px-3 py-2">{{ $subTeamTargetAchievement['percent'] }}%</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Achieved (this month)</span>
                    <span class="fw-semibold">${{ number_format($subTeamTargetAchievement['achieved'], 0) }} <span class="text-muted fw-normal">/ ${{ number_format($subTeamTargetAchievement['target'], 0) }}</span></span>
                </div>
                <div class="progress rounded-pill" style="height:12px">
                    <div class="progress-bar bg-success rounded-pill" style="width:{{ min(100, $subTeamTargetAchievement['percent'] ?? 0) }}%"></div>
                </div>
                @if($subTeamTargetAchievement['target'] <= 0)
                    <p class="text-muted small mb-0 mt-2">No sub-team target set for this month.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header border-0">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-semibold mb-1"><i class="bi bi-people-fill text-success"></i> {{ $subTeamHead->title }} members</h5>
                <p class="text-muted small mb-0">{{ $currentMonthLabel }} · sales, revenue &amp; targets (same month)</p>
            </div>
            <span class="badge bg-info">{{ $subTeamMembers->count() }} members</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Member</th>
                        <th>Role</th>
                        <th class="text-center">{{ $currentMonthLabel }} Sales</th>
                        <th class="text-end">{{ $currentMonthLabel }} Revenue</th>
                        <th class="text-end">{{ $currentMonthLabel }} Target</th>
                        <th class="text-center">Achievement</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($memberStats as $member)
                    <tr class="{{ $member['is_sub_team_head'] ? 'table-primary' : '' }}">
                        <td>
                            <strong>{{ $member['name'] }}</strong>
                            @if($member['is_sub_team_head'])
                                <span class="badge bg-info text-dark ms-1" style="font-size:10px">Head</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $member['role_name'] }}</td>
                        <td class="text-center">{{ $member['sales_count'] }}</td>
                        <td class="text-end text-success fw-semibold">${{ number_format($member['month_revenue'], 0) }}</td>
                        <td class="text-end">${{ number_format($member['month_target'], 0) }}</td>
                        <td class="text-center">
                            @if($member['target_achievement_pct'] !== null)
                                @php
                                    $pct = $member['target_achievement_pct'];
                                    $badgeColor = $pct >= 100 ? 'success' : ($pct >= 60 ? 'warning' : 'danger');
                                @endphp
                                <span class="badge bg-{{ $badgeColor }}">{{ $pct }}%</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted text-center py-3">No members in this sub-team.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="text-end fw-semibold">Sub-team total</td>
                        <td class="text-end fw-bold text-success">${{ number_format($subTeamTargetAchievement['achieved'], 0) }}</td>
                        <td class="text-end">${{ number_format($subTeamTargetAchievement['target'], 0) }}</td>
                        <td class="text-center">
                            @if($subTeamTargetAchievement['percent'] !== null)
                                <span class="badge bg-primary">{{ $subTeamTargetAchievement['percent'] }}%</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm dashboard-chart-card">
            <div class="card-header border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-graph-up-arrow text-primary"></i> Sub-team revenue trend</h6>
                <span class="text-muted small">Last 14 days · approved &amp; completed</span>
            </div>
            <div class="card-body pt-2">
                <div class="dashboard-chart-wrap" style="height:260px;">
                    <canvas id="subTeamRevenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-cash-stack text-primary"></i> Recent sales (sub-team)</span>
        <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Agent</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSales as $sale)
                    @php $sc2 = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger']; @endphp
                    <tr>
                        <td class="text-muted small">{{ $sale->sale_date?->format('d M Y') ?? '—' }}</td>
                        <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                        <td class="text-muted small">{{ $sale->user?->name }}</td>
                        <td class="text-muted small">{{ $sale->client_name }}</td>
                        <td><span class="badge bg-{{ $sc2[$sale->status] ?? 'secondary' }}">{{ ucfirst($sale->status) }}</span></td>
                        <td class="text-end fw-semibold">${{ number_format($sale->amount, 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted text-center py-3">No sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@elseif($isTeamHead)
{{-- ═══════════════════════════════════════════════════════════════
     TEAM HEAD DASHBOARD
═══════════════════════════════════════════════════════════════ --}}

{{-- Company + Team info --}}
<!-- <div class="row g-3 mb-4">
    {{-- My Company --}}
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    @if($company?->logo)
                        <img src="{{ asset('storage/' . $company->logo) }}" alt="{{ $company->name }}"
                             style="height:56px;width:56px;object-fit:cover;border-radius:10px;border:1px solid #dee2e6;flex-shrink:0;">
                    @else
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-buildings fs-4 text-primary"></i>
                        </div>
                    @endif
                    <div>
                        <div class="text-muted small">My Company</div>
                        <div class="fw-bold fs-5">{{ $company?->name ?? 'Not assigned' }}</div>
                        @if($company)
                        <div class="small text-muted">
                            {{ $companyStats['teams_count'] }} teams &middot;
                            {{ $companyStats['users_count'] }} users &middot;
                            {{ $companyStats['sales_count'] }} sales
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- My Team --}}
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-people fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">My Team</div>
                        <div class="fw-bold fs-5">{{ $teams->first()?->name ?? 'Not assigned' }}</div>
                        @if($teams->first())
                        <div class="small text-muted">
                            {{ $teams->first()->users->count() }} members &middot;
                            Head: {{ $teams->first()->teamHead?->name ?? 'N/A' }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> -->

@php
    $thMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');
    $uta = $userTargetAchievement ?? ['target' => 0, 'achieved' => 0, 'percent' => null];
    $thMyTarget = (float) ($uta['target'] ?? 0);
    $thMyRevenue = (float) ($uta['achieved'] ?? ($mySales['month_revenue'] ?? $mySales['revenue'] ?? 0));
    $thMyPct = $uta['percent'] ?? null;
@endphp
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small">My target achievement</div>
                        <div class="fw-semibold">{{ $thMonth }}</div>
                    </div>
                    @if($thMyPct !== null)
                        <span class="badge rounded-pill bg-primary px-3 py-2">{{ $thMyPct }}%</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Achieved (this month)</span>
                    <span class="fw-semibold">${{ number_format($thMyRevenue, 0) }} <span class="text-muted fw-normal">/ ${{ number_format($thMyTarget, 0) }}</span></span>
                </div>
                <div class="progress rounded-pill" style="height:12px">
                    <div class="progress-bar bg-primary rounded-pill" style="width:{{ $thMyPct !== null ? min(100, $thMyPct) : 0 }}%"></div>
                </div>
                @if($thMyTarget <= 0)
                    <p class="text-muted small mb-0 mt-2">No personal target set for this month.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                @php $ta = $teamTargetAchievement; $tp = $ta['percent']; @endphp
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small">Team target achievement</div>
                        <div class="fw-semibold">{{ $ta['label'] }}
                            @if($teams->first())<span class="text-muted"> · {{ $teams->first()->name }}</span>@endif
                        </div>
                    </div>
                    @if($tp !== null)
                        <span class="badge rounded-pill bg-success px-3 py-2">{{ $tp }}%</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Achieved (this month)</span>
                    <span class="fw-semibold">${{ number_format($ta['achieved'], 0) }} <span class="text-muted fw-normal">/ ${{ number_format($ta['target'], 0) }}</span></span>
                </div>
                <div class="progress rounded-pill" style="height:12px">
                    <div class="progress-bar bg-success rounded-pill" style="width:{{ $tp !== null ? min(100, $tp) : 0 }}%"></div>
                </div>
                @if(($ta['target'] ?? 0) <= 0)
                    <p class="text-muted small mb-0 mt-2">No team target set for this month.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Team month KPIs (not personal all-time) --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Team revenue ({{ $thMonth }})</div>
                <div class="display-6 fw-bold text-warning">${{ number_format($teamTargetAchievement['achieved'] ?? 0, 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Team sales ({{ $thMonth }})</div>
                <div class="display-6 fw-bold text-info">{{ $teamMonthSalesCount ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">My revenue ({{ $thMonth }})</div>
                <div class="display-6 fw-bold text-primary">${{ number_format($thMyRevenue, 0) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Charts: team revenue trend + team sales mix --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-graph-up-arrow text-primary"></i> Team revenue</h6>
                <span class="text-muted small">Last 14 days · approved &amp; completed</span>
            </div>
            <div class="card-body pt-2">
                <div class="dashboard-chart-wrap" style="height:260px;">
                    <canvas id="agentRevenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-pie-chart-fill text-primary"></i> Team sales mix</h6>
                <span class="text-muted small">{{ $thMonth }} · by status</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center pt-2" style="min-height:260px;">
                <canvas id="agentStatusChart" style="max-height:240px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Sub-Teams Progress --}}
@if(isset($subTeamsProgress) && $subTeamsProgress->isNotEmpty())
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white border-0">
        <h5 class="fw-semibold mb-1"><i class="bi bi-diagram-3 text-info"></i> Sub-Teams Progress</h5>
        <p class="text-muted small mb-0">{{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y') }} · Target vs Achievement</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($subTeamsProgress as $subTeam)
            <div class="col-md-6">
                <div class="card border h-100" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="fw-bold mb-1">{{ $subTeam['title'] }}</h6>
                                <small class="text-muted">Head: {{ $subTeam['head_name'] }} · {{ $subTeam['members_count'] }} members</small>
                            </div>
                            @if($subTeam['percent'] !== null)
                                @php
                                    $pct = $subTeam['percent'];
                                    $badgeColor = $pct >= 100 ? 'success' : ($pct >= 60 ? 'warning' : 'danger');
                                @endphp
                                <span class="badge bg-{{ $badgeColor }} px-3 py-2">{{ $pct }}%</span>
                            @else
                                <span class="badge bg-secondary px-3 py-2">—</span>
                            @endif
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="text-muted">Target</span>
                            <span class="fw-semibold">${{ number_format($subTeam['target'], 0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="text-muted">Achieved</span>
                            <span class="fw-semibold text-success">${{ number_format($subTeam['revenue'], 0) }}</span>
                        </div>
                        <div class="progress rounded-pill" style="height:10px">
                            <div class="progress-bar bg-{{ $badgeColor ?? 'secondary' }} rounded-pill" 
                                 style="width:{{ min(100, $subTeam['percent'] ?? 0) }}%"></div>
                        </div>
                        @if($subTeam['target'] <= 0)
                            <p class="text-muted small mb-0 mt-2">No target set for this sub-team.</p>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Team cards with members --}}
@foreach($teamDashboardCards as $card)
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ $card['name'] }}</strong>
            <span class="text-muted ms-2 small">{{ $card['company_name'] }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary">{{ $card['members_count'] }} members</span>
            @if($card['achievement_percent'] !== null)
                <span class="badge bg-success">{{ $card['achievement_percent'] }}% achieved</span>
            @endif
        </div>
    </div>
    <div class="card-body">
        @if($card['target'] > 0)
        <div class="mb-3">
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">{{ $card['month_label'] }} — Team Target</span>
                <span class="fw-semibold">${{ number_format($card['monthly_revenue'], 0) }} / ${{ number_format($card['target'], 0) }}</span>
            </div>
            <div class="progress rounded-pill" style="height:10px">
                <div class="progress-bar rounded-pill" style="width:{{ min(100, $card['achievement_percent'] ?? 0) }}%"></div>
            </div>
        </div>
        @endif
        @if(($card['ppc_spending'] ?? 0) > 0 && auth()->user()->hasRole('Admin'))
        <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded bg-light border">
            <i class="bi bi-graph-up text-danger"></i>
            <span class="small text-muted">PPC Spending — {{ $card['month_label'] }}</span>
            <span class="ms-auto fw-bold text-danger">${{ number_format($card['ppc_spending'], 2) }}</span>
        </div>
        @endif
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Member</th>
                        <th>Role</th>
                        <th class="text-center">{{ $card['month_label'] }} Sales</th>
                        <th class="text-end">{{ $card['month_label'] }} Revenue</th>
                        <th class="text-end">{{ $card['month_label'] }} Target</th>
                        <th class="text-center">Achieve</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($card['members'] as $member)
                    <tr class="{{ $member['id'] === auth()->id() ? 'table-primary' : '' }}">
                        <td>
                            {{ $member['name'] }}
                            @if($member['is_head'])
                                <span class="badge bg-warning text-dark ms-1" style="font-size:10px">Head</span>
                            @endif
                            @if($member['id'] === auth()->id())
                                <span class="badge bg-primary ms-1" style="font-size:10px">You</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $member['role_name'] }}</td>
                        <td class="text-center">{{ $member['sales_count'] }}</td>
                        <td class="text-end text-success">${{ number_format($member['sale_amount'], 0) }}</td>
                        <td class="text-end">${{ number_format($member['month_target'], 0) }}</td>
                        <td class="text-center">
                            @if($member['target_achievement_pct'] !== null)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">{{ $member['target_achievement_pct'] }}%</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted text-center py-3">No members.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endforeach

{{-- Team recent sales --}}
<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-cash-stack text-primary"></i> Team recent sales</span>
        <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Title</th><th>Agent</th><th>Client</th><th>Status</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    @forelse($recentSales as $sale)
                    @php $sc2 = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger']; @endphp
                    <tr>
                        <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                        <td class="text-muted small">{{ $sale->user?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $sale->client_name }}</td>
                        <td><span class="badge bg-{{ $sc2[$sale->status] ?? 'secondary' }}">{{ ucfirst($sale->status) }}</span></td>
                        <td class="text-end fw-semibold">${{ number_format($sale->amount, 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted text-center py-3">No sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@elseif($isAgent)
{{-- ═══════════════════════════════════════════════════════════════
     AGENT DASHBOARD
═══════════════════════════════════════════════════════════════ --}}

<!-- <div class="row g-3 mb-4">
    {{-- My Company --}}
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    @if($company?->logo)
                        <img src="{{ asset('storage/' . $company->logo) }}" alt="{{ $company->name }}"
                             style="height:56px;width:56px;object-fit:cover;border-radius:10px;border:1px solid #dee2e6;flex-shrink:0;">
                    @else
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-buildings fs-4 text-primary"></i>
                        </div>
                    @endif
                    <div>
                        <div class="text-muted small">My Company</div>
                        <div class="fw-bold fs-5">{{ $company?->name ?? 'Not assigned' }}</div>
                        @if($company)
                        <div class="small text-muted">
                            {{ $companyStats['teams_count'] }} teams &middot;
                            {{ $companyStats['users_count'] }} users &middot;
                            {{ $companyStats['sales_count'] }} sales
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- My Team --}}
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-people fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">My Team</div>
                        <div class="fw-bold fs-5">{{ $team?->name ?? 'Not assigned' }}</div>
                        @if($team)
                        <div class="small text-muted">
                            {{ $team->users()->count() }} members &middot;
                            Head: {{ $team->teamHead?->name ?? 'N/A' }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> -->

@php
    $agentMonth = $currentMonthLabel ?? \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');
    $ua = $userTargetAchievement;
    $ta = $teamTargetAchievement;
    $up = $ua['percent'];
    $tp = $ta['percent'];
@endphp
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small">My target achievement</div>
                        <div class="fw-semibold">{{ $ua['label'] }}</div>
                    </div>
                    @if($up !== null)
                        <span class="badge rounded-pill bg-primary px-3 py-2">{{ $up }}%</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Achieved (approved this month)</span>
                    <span class="fw-semibold">${{ number_format($ua['achieved'], 0) }} <span class="text-muted fw-normal">/ ${{ number_format($ua['target'], 0) }}</span></span>
                </div>
                <div class="progress rounded-pill" style="height: 12px;">
                    <div class="progress-bar bg-primary rounded-pill" role="progressbar" style="width: {{ $up !== null ? min(100, $up) : 0 }}%;" aria-valuenow="{{ $up ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                @if(($ua['target'] ?? 0) <= 0)
                    <p class="text-muted small mb-0 mt-2">No personal target set for this month.</p>
                @elseif(($mySales['pending_approval'] ?? 0) > 0)
                    <p class="text-muted small mb-0 mt-2">{{ $mySales['pending_approval'] }} sale(s) awaiting approval — not counted yet.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small">Team target achievement</div>
                        <div class="fw-semibold">{{ $ta['label'] }} @if($team)<span class="text-muted">· {{ $team->name }}</span>@endif</div>
                    </div>
                    @if($tp !== null)
                        <span class="badge rounded-pill bg-success px-3 py-2">{{ $tp }}%</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Achieved (approved this month)</span>
                    <span class="fw-semibold">${{ number_format($ta['achieved'], 0) }} <span class="text-muted fw-normal">/ ${{ number_format($ta['target'], 0) }}</span></span>
                </div>
                <div class="progress rounded-pill" style="height: 12px;">
                    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: {{ $tp !== null ? min(100, $tp) : 0 }}%;" aria-valuenow="{{ $tp ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                @if(!$team || ($ta['target'] ?? 0) <= 0)
                    <p class="text-muted small mb-0 mt-2">{{ !$team ? 'No team assigned.' : 'No team target set for this month.' }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 text-center">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">My revenue ({{ $agentMonth }})</div>
                <div class="display-6 fw-bold text-warning">${{ number_format($mySales['revenue'], 0) }}</div>
                <div class="text-muted small mt-1">Approved &amp; completed</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 text-center">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Approved sales ({{ $agentMonth }})</div>
                <div class="display-6 fw-bold text-info">{{ $mySales['completed'] }}</div>
                <div class="text-muted small mt-1">Of {{ $mySales['total'] }} submitted</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 text-center">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Awaiting approval</div>
                <div class="display-6 fw-bold text-primary">{{ $mySales['pending_approval'] ?? 0 }}</div>
                <div class="text-muted small mt-1">{{ $agentMonth }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header border-0 pb-0">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="fw-semibold mb-0"><i class="bi bi-graph-up-arrow text-primary"></i> My revenue</h6>
                        <span class="text-muted small">Last 14 days · approved &amp; completed</span>
                    </div>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="dashboard-chart-wrap" style="height: 260px;">
                    <canvas id="agentRevenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-pie-chart-fill text-primary"></i> My sales mix</h6>
                <span class="text-muted small">{{ $agentMonth }} · by approval</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center pt-2" style="min-height: 260px;">
                <canvas id="agentStatusChart" style="max-height: 240px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-cash-stack text-primary"></i> My recent sales</span>
                <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th>Approval</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSales as $sale)
                            @php
                                $sc2 = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger','refunded'=>'secondary'];
                                $ac = ['approved'=>'success','rejected'=>'danger','pending_approval'=>'warning'];
                                $al = ['approved'=>'Approved','rejected'=>'Rejected','pending_approval'=>'Awaiting'];
                            @endphp
                            <tr>
                                <td class="text-muted small">{{ $sale->sale_date?->format('d M Y') ?? '—' }}</td>
                                <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                                <td class="text-muted small">{{ $sale->client_name }}</td>
                                <td><span class="badge bg-{{ $sc2[$sale->status] ?? 'secondary' }}">{{ ucfirst($sale->status) }}</span></td>
                                <td><span class="badge bg-{{ $ac[$sale->approval_status] ?? 'secondary' }}">{{ $al[$sale->approval_status] ?? $sale->approval_status }}</span></td>
                                <td class="text-end fw-semibold">${{ number_format($sale->amount, 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-muted text-center py-3">No sales yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@else
<button type="button" class="dash-more-toggle" id="dash-more-toggle" aria-expanded="false" aria-controls="dash-more">
    <span>Show detailed team and sales breakdown</span>
    <i class="bi bi-chevron-down"></i>
</button>
<div id="dash-more" hidden>
@php $tach = $targetAchievement; @endphp
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="dash-card-title">Teams · {{ $tach['label'] ?? '' }}</div>
            <span class="text-muted" style="font-size:12px">Member lists stay closed until you open a team</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Team</th>
                        @unless($singleCompany)<th>Company</th>@endunless
                        <th class="text-center">Members</th>
                        <th class="text-end">This month</th>
                        <th class="text-end">Target</th>
                        <th class="text-center">Achieve</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($teamDashboardCards ?? collect()) as $tc)
                    <tr>
                        <td class="fw-semibold">
                            {{ $tc['name'] }}
                            @if(($tc['ppc_spending'] ?? 0) > 0)
                                <div class="text-danger" style="font-size:11px;font-weight:500">PPC ${{ number_format($tc['ppc_spending'], 0) }}</div>
                            @endif
                        </td>
                        @unless($singleCompany)
                        <td class="text-muted">{{ $tc['company_name'] ?: '—' }}</td>
                        @endunless
                        <td class="text-center">{{ $tc['members_count'] }}</td>
                        <td class="text-end">${{ number_format($tc['monthly_revenue'], 0) }}</td>
                        <td class="text-end">${{ number_format($tc['target'], 0) }}</td>
                        <td class="text-center">{{ $tc['achievement_percent'] !== null ? $tc['achievement_percent'].'%' : '—' }}</td>
                        <td class="text-end">
                            @if(!empty($tc['members']))
                            <button type="button" class="btn btn-sm btn-outline-secondary js-team-members" aria-expanded="false" aria-controls="team-members-{{ $tc['id'] }}">Members</button>
                            @endif
                        </td>
                    </tr>
                    @if(!empty($tc['members']))
                    <tr id="team-members-{{ $tc['id'] }}" hidden>
                        <td colspan="{{ $singleCompany ? 6 : 7 }}" class="bg-light">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th class="text-center">Sales</th>
                                        <th class="text-end">Revenue</th>
                                        <th class="text-end">Month target</th>
                                        <th class="text-end">Month revenue</th>
                                        <th class="text-center">Achieve</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tc['members'] as $m)
                                    <tr>
                                        <td>
                                            {{ $m['name'] }}
                                            @if(!empty($m['is_head']))
                                                <span class="badge bg-warning text-dark ms-1" style="font-size:10px">Head</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $m['role_name'] }}</td>
                                        <td class="text-center">{{ $m['sales_count'] }}</td>
                                        <td class="text-end">${{ number_format($m['sale_amount'], 0) }}</td>
                                        <td class="text-end">${{ number_format($m['month_target'], 0) }}</td>
                                        <td class="text-end">${{ number_format($m['month_revenue'], 0) }}</td>
                                        <td class="text-center">{{ $m['target_achievement_pct'] !== null ? $m['target_achievement_pct'].'%' : '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr><td colspan="{{ $singleCompany ? 6 : 7 }}" class="text-muted text-center py-3">No teams yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Companies bar + approval pipeline --}}
<div class="row g-3 mb-4">
    @unless($singleCompany)
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-bar-chart-steps text-success"></i> Revenue by company</h6>
                <span class="text-muted small">Top 8 · completed revenue</span>
            </div>
            <div class="card-body pt-2">
                <div class="dashboard-chart-wrap" style="height: 280px;">
                    <canvas id="companyBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endunless
    <div class="{{ $singleCompany ? 'col-lg-6' : 'col-lg-5' }}">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-shield-check text-warning"></i> Approval pipeline</h6>
                <span class="text-muted small">All sales</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center pt-2" style="min-height: 280px;">
                <canvas id="approvalChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    @unless($singleCompany)
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="dash-card-title mb-2">Company breakdown</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Company</th>
                                <th class="text-center">Teams</th>
                                <th class="text-center">Users</th>
                                <th class="text-center">Sales</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">{{ \DateTime::createFromFormat('!m', $month)->format('M') }} target</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($companies as $company)
                            <tr>
                                <td class="fw-semibold">{{ $company->name }}</td>
                                <td class="text-center">{{ $company->teams_count }}</td>
                                <td class="text-center">{{ $company->users_count }}</td>
                                <td class="text-center">{{ $company->sales_count }}</td>
                                <td class="text-end">${{ number_format($company->revenue, 0) }}</td>
                                <td class="text-end">${{ number_format($company->team_target, 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-muted text-center py-3">No companies yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endunless
    <div class="{{ $singleCompany ? 'col-lg-6' : 'col-lg-5' }}">
        <div class="card h-100">
            <div class="card-body">
                <div class="dash-card-title mb-2">Top agents</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Agent</th>
                                <th>Team</th>
                                <th class="text-center">Sales</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topAgents as $agent)
                            <tr>
                                <td class="fw-semibold">{{ $agent->name }}</td>
                                <td class="text-muted">{{ $agent->team?->name ?? '—' }}</td>
                                <td class="text-center">{{ $agent->total_sales }}</td>
                                <td class="text-end">${{ number_format($agent->total_amount, 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

@endif
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const brand = { blue: '#2E86C1', purple: '#6B3A7D', green: '#198754', amber: '#f0ad4e', red: '#dc3545' };
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = '#5c6378';

@if(($isAgent ?? false) || ($isTeamHead ?? false) || ($isSubTeamHead ?? false))
    const arCtx = document.getElementById('agentRevenueChart') || document.getElementById('subTeamRevenueChart');
    if (arCtx) {
        const g = arCtx.getContext('2d').createLinearGradient(0, 0, 0, 260);
        g.addColorStop(0, 'rgba(46, 134, 193, 0.35)');
        g.addColorStop(1, 'rgba(46, 134, 193, 0.02)');
        new Chart(arCtx, {
            type: 'line',
            data: {
                labels: @json(($isSubTeamHead ?? false) ? ($revenueTrendLabels ?? []) : ($agentRevenueTrendLabels ?? [])),
                datasets: [{
                    label: 'Revenue',
                    data: @json(($isSubTeamHead ?? false) ? ($revenueTrendValues ?? []) : ($agentRevenueTrendValues ?? [])),
                    borderColor: brand.blue,
                    backgroundColor: g,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: brand.blue,
                    pointBorderWidth: 2,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' } }
                }
            }
        });
    }
    const asCtx = document.getElementById('agentStatusChart');
    if (asCtx) {
        new Chart(asCtx, {
            type: 'doughnut',
            data: {
                labels: @json($agentSalesByStatus['labels'] ?? []),
                datasets: [{
                    data: @json($agentSalesByStatus['values'] ?? []),
                    backgroundColor: [brand.green, brand.amber, brand.red],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 14, padding: 16, usePointStyle: true } }
                }
            }
        });
    }
@else
    function drawDetailCharts() {
    const rt = document.getElementById('revenueTrendChart');
    if (rt && !rt.dataset.ready) {
        rt.dataset.ready = '1';
        const grad = rt.getContext('2d').createLinearGradient(0, 0, 0, 300);
        grad.addColorStop(0, 'rgba(46, 134, 193, 0.4)');
        grad.addColorStop(0.5, 'rgba(107, 58, 125, 0.12)');
        grad.addColorStop(1, 'rgba(160, 24, 48, 0.02)');
        new Chart(rt, {
            type: 'line',
            data: {
                labels: @json($revenueTrendLabels ?? []),
                datasets: [{
                    label: 'Revenue',
                    data: @json($revenueTrendValues ?? []),
                    borderColor: brand.blue,
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: brand.purple,
                    pointBorderWidth: 2,
                    borderWidth: 2.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' $' + Number(ctx.raw).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' } }
                }
            }
        });
    }

    const sc = document.getElementById('salesChart');
    if (sc && !sc.dataset.ready) {
        sc.dataset.ready = '1';
        new Chart(sc, {
            type: 'doughnut',
            data: {
                labels: @json(isset($salesByStatus) ? $salesByStatus->keys()->map(fn($s) => ucfirst($s)) : []),
                datasets: [{
                    data: @json(isset($salesByStatus) ? $salesByStatus->values() : []),
                    backgroundColor: [brand.amber, brand.green, brand.red],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 14, padding: 14, usePointStyle: true } }
                }
            }
        });
    }

    const cb = document.getElementById('companyBarChart');
    if (cb && !cb.dataset.ready) {
        cb.dataset.ready = '1';
        new Chart(cb, {
            type: 'bar',
            data: {
                labels: @json($companyBarLabels ?? []),
                datasets: [{
                    label: 'Revenue',
                    data: @json($companyBarValues ?? []),
                    backgroundColor: function (ctx) {
                        const a = ['rgba(46,134,193,0.88)','rgba(107,58,125,0.88)','rgba(160,24,48,0.78)','rgba(25,135,84,0.82)','rgba(240,173,78,0.92)','rgba(46,134,193,0.6)','rgba(107,58,125,0.6)','rgba(160,24,48,0.6)'];
                        return a[ctx.dataIndex % a.length];
                    },
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' }, ticks: {
                        callback: function (v) { return '$' + Number(v).toLocaleString(); }
                    }},
                    y: { grid: { display: false } }
                }
            }
        });
    }

    const ap = document.getElementById('approvalChart');
    if (ap && !ap.dataset.ready) {
        ap.dataset.ready = '1';
        new Chart(ap, {
            type: 'doughnut',
            data: {
                labels: @json(array_keys($approvalMix ?? [])),
                datasets: [{
                    data: @json(array_values($approvalMix ?? [])),
                    backgroundColor: [brand.green, brand.amber, brand.red],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 14, padding: 12, usePointStyle: true } }
                }
            }
        });
    }
    }

    function drawBossCharts() {
        const raw = document.getElementById('boss-dash-data');
        if (!raw || !window.Chart) return;
        const data = JSON.parse(raw.textContent);
        const money = function (v) { return '$' + Number(v).toLocaleString(); };
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const tickColor = isDark ? '#9ca3af' : '#64748b';
        const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const barColor = isDark ? '#60a5fa' : '#1e293b';
        const donutBorder = isDark ? '#1a1d27' : '#ffffff';
        const legendColor = isDark ? '#e4e6eb' : '#334155';

        const donut = function (id, pack, colors) {
            const node = document.getElementById(id);
            if (!node || !pack.values || !pack.values.length) return;
            if (node._crmChart) { node._crmChart.destroy(); }
            node._crmChart = new Chart(node, {
                type: 'doughnut',
                data: { labels: pack.labels, datasets: [{ data: pack.values, backgroundColor: colors, borderWidth: 2, borderColor: donutBorder }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 8, padding: 10, usePointStyle: true, font: { size: 11 }, color: legendColor }
                        }
                    }
                }
            });
        };

        const revenue = document.getElementById('bossRevenueChart');
        if (revenue) {
            if (revenue._crmChart) { revenue._crmChart.destroy(); }
            revenue.dataset.ready = '1';
            revenue._crmChart = new Chart(revenue, {
                type: 'bar',
                data: {
                    labels: data.revenue.labels,
                    datasets: [{
                        data: data.revenue.values,
                        backgroundColor: barColor,
                        hoverBackgroundColor: isDark ? '#93c5fd' : '#334155',
                        borderRadius: 4,
                        maxBarThickness: 28
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: function (c) { return ' ' + money(c.raw); } } }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: tickColor }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: gridColor },
                            ticks: { color: tickColor, callback: function (v) { return money(v); } }
                        }
                    }
                }
            });
        }
        donut('bossInvoiceChart', data.invoices, ['#22c55e', '#f59e0b', '#ef4444']);
        donut('bossLeadChart', data.leads, ['#3b82f6', '#6366f1', '#06b6d4', '#f59e0b', '#94a3b8', '#22c55e', '#ef4444', '#8b5cf6', '#14b8a6', '#eab308']);
    }
    drawBossCharts();
    window.addEventListener('crm:themechange', function () { drawBossCharts(); });

    const dashMore = document.getElementById('dash-more');
    const dashToggle = document.getElementById('dash-more-toggle');
    if (dashMore && dashToggle) {
        dashToggle.addEventListener('click', function () {
            const willOpen = dashMore.hasAttribute('hidden');
            if (willOpen) {
                dashMore.removeAttribute('hidden');
                dashToggle.setAttribute('aria-expanded', 'true');
                dashToggle.querySelector('span').textContent = 'Hide detailed team and sales breakdown';
                drawDetailCharts();
                setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 80);
            } else {
                dashMore.setAttribute('hidden', '');
                dashToggle.setAttribute('aria-expanded', 'false');
                dashToggle.querySelector('span').textContent = 'Show detailed team and sales breakdown';
            }
        });
        dashMore.addEventListener('click', function (event) {
            const btn = event.target.closest('.js-team-members');
            if (!btn) return;
            const row = document.getElementById(btn.getAttribute('aria-controls'));
            if (!row) return;
            const willOpen = row.hasAttribute('hidden');
            if (willOpen) row.removeAttribute('hidden');
            else row.setAttribute('hidden', '');
            btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            btn.textContent = willOpen ? 'Hide' : 'Members';
        });
    }
@endif
})();
</script>
@endsection
