@extends('admin.layout')

@push('styles')
<style>
    .dashboard-chart-card { border-radius: 12px; overflow: hidden; }
    .dashboard-chart-wrap { position: relative; width: 100%; }
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

{{-- Team Target + Sub-Team Target --}}
@php
    $teamTargetObj = \App\Models\TeamTarget::where('team_id', $team->id)
        ->where('month', $month)
        ->where('year', $year)
        ->first();
    $teamTargetAmt = (float) ($teamTargetObj?->target_amount ?? 0);
    $curMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');
@endphp

<div class="row g-3 mb-4">
    {{-- Team Target (Read-only) --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small"><i class="bi bi-flag"></i> Team Target</div>
                        <div class="fw-semibold">{{ $curMonth }} · {{ $team->name }}</div>
                    </div>
                </div>
                <div class="display-6 fw-bold text-primary">${{ number_format($teamTargetAmt, 0) }}</div>
                @if($teamTargetAmt <= 0)
                    <p class="text-muted small mb-0 mt-2">No team target set for this month.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Sub-Team Target --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="small"><i class="bi bi-flag-fill"></i> Sub-Team Target</div>
                        <div class="fw-semibold">{{ $curMonth }} · {{ $subTeamHead->title }}</div>
                    </div>
                    @if($subTeamTargetAchievement['percent'] !== null)
                        <span class="badge rounded-pill bg-white text-dark px-3 py-2">{{ $subTeamTargetAchievement['percent'] }}%</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span>Achieved</span>
                    <span class="fw-semibold">${{ number_format($subTeamTargetAchievement['achieved'], 0) }} / ${{ number_format($subTeamTargetAchievement['target'], 0) }}</span>
                </div>
                <div class="progress rounded-pill mb-2" style="height:12px; background: rgba(255,255,255,0.3);">
                    <div class="progress-bar bg-white rounded-pill" style="width:{{ min(100, $subTeamTargetAchievement['percent'] ?? 0) }}%"></div>
                </div>
                @if($subTeamTargetAchievement['target'] <= 0)
                    <p class="small mb-0">No sub-team target set for this month.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Sub-Team Members Performance Table --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-semibold mb-1"><i class="bi bi-people-fill text-success"></i> {{ $subTeamHead->title }} Team Members</h5>
                <p class="text-muted small mb-0">{{ $curMonth }} · Target vs Achievement</p>
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
                        <th class="text-center">Sales (All Time)</th>
                        <th class="text-end">Revenue (All Time)</th>
                        <th class="text-end">{{ $curMonth }} Target</th>
                        <th class="text-end">{{ $curMonth }} Revenue</th>
                        <th class="text-center">Achievement</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($memberStats as $member)
                    <tr class="{{ $member['is_sub_team_head'] ? 'table-primary' : '' }}">
                        <td>
                            <strong>{{ $member['name'] }}</strong>
                            @if($member['is_sub_team_head'])
                                <span class="badge bg-info text-dark ms-1" style="font-size:10px">Sub-Team Head</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $member['role_name'] }}</td>
                        <td class="text-center">{{ $member['sales_count'] }}</td>
                        <td class="text-end text-success fw-semibold">${{ number_format($member['sale_amount'], 0) }}</td>
                        <td class="text-end">${{ number_format($member['month_target'], 0) }}</td>
                        <td class="text-end fw-semibold">${{ number_format($member['month_revenue'], 0) }}</td>
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
                    <tr><td colspan="7" class="text-muted text-center py-3">No members in this sub-team.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="text-end fw-semibold">Sub-Team Total:</td>
                        <td class="text-end fw-bold text-success">${{ number_format($subTeamTargetAchievement['achieved'], 0) }}</td>
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

{{-- Revenue Trend Chart --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm dashboard-chart-card">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-graph-up-arrow text-primary"></i> Sub-Team Revenue Trend</h6>
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

{{-- Recent Sales from Sub-Team --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-cash-stack text-primary"></i> Recent Sales (Sub-Team)</span>
        <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
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
                        <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                        <td class="text-muted small">{{ $sale->user?->name }}</td>
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

{{-- My target achievement --}}
@php
    $thMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');
    $thMyTarget = $myTarget?->target_amount ?? 0;
    $thMyRevenue = $mySales['revenue'];
    $thMyPct = $thMyTarget > 0 ? min(100, round(($thMyRevenue / $thMyTarget) * 100, 1)) : null;
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
                    <div class="progress-bar bg-primary rounded-pill" style="width:{{ $thMyPct ?? 0 }}%"></div>
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
{{-- Revenue + Sales count --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">My Revenue (all time)</div>
                <div class="display-6 fw-bold text-warning">${{ number_format($mySales['revenue'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Total Sales</div>
                <div class="display-6 fw-bold text-info">{{ $mySales['total'] }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Charts: revenue trend + sales mix --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-graph-up-arrow text-primary"></i> My revenue</h6>
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
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-pie-chart-fill text-primary"></i> My sales mix</h6>
                <span class="text-muted small">By status</span>
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
                        <th class="text-center">Sales</th>
                        <th class="text-end">Revenue</th>
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
                    <tr><th>Title</th><th>Client</th><th>Status</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    @forelse($recentSales as $sale)
                    @php $sc2 = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger']; @endphp
                    <tr>
                        <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                        <td class="text-muted small">{{ $sale->client_name }}</td>
                        <td><span class="badge bg-{{ $sc2[$sale->status] ?? 'secondary' }}">{{ ucfirst($sale->status) }}</span></td>
                        <td class="text-end fw-semibold">${{ number_format($sale->amount, 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted text-center py-3">No sales yet.</td></tr>
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

{{-- Target achievement + revenue --}}
@php
    $curMonth = \DateTime::createFromFormat('!m', $month)->format('F');
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
                    <span class="text-muted">Achieved (this month)</span>
                    <span class="fw-semibold">${{ number_format($ua['achieved'], 0) }} <span class="text-muted fw-normal">/ ${{ number_format($ua['target'], 0) }}</span></span>
                </div>
                <div class="progress rounded-pill" style="height: 12px;">
                    <div class="progress-bar bg-primary rounded-pill" role="progressbar" style="width: {{ $up !== null ? min(100, $up) : 0 }}%;" aria-valuenow="{{ $up ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                @if(($ua['target'] ?? 0) <= 0)
                    <p class="text-muted small mb-0 mt-2">No personal target set for this month.</p>
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
                    <span class="text-muted">Achieved (this month)</span>
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
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 text-center">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">My revenue (all time)</div>
                <div class="display-6 fw-bold text-warning">${{ number_format($mySales['revenue'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 text-center">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Total sales (all)</div>
                <div class="display-6 fw-bold text-info">{{ $mySales['total'] }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Sales by status mini cards --}}
<!-- <div class="row g-3 mb-4">
    @php $sc = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger']; @endphp
    @foreach($sc as $s => $color)
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fw-bold fs-4 text-{{ $color }}">{{ $mySales[$s] }}</div>
                <div class="text-muted small">{{ ucfirst($s) }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div> -->

{{-- Charts: my revenue trend + sales mix --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header bg-white border-0 pb-0">
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
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-pie-chart-fill text-primary"></i> My sales mix</h6>
                <span class="text-muted small">By status</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center pt-2" style="min-height: 260px;">
                <canvas id="agentStatusChart" style="max-height: 240px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- My Recent Sales — full width for agent --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-cash-stack text-primary"></i> My Recent Sales</span>
                <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSales as $sale)
                            @php $sc2 = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger']; @endphp
                            <tr>
                                <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                                <td class="text-muted small">{{ $sale->client_name }}</td>
                                <td><span class="badge bg-{{ $sc2[$sale->status] ?? 'secondary' }}">{{ ucfirst($sale->status) }}</span></td>
                                <td class="text-end fw-semibold">${{ number_format($sale->amount, 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No sales yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@else
{{-- ═══════════════════════════════════════════════════════════════
     ADMIN DASHBOARD
═══════════════════════════════════════════════════════════════ --}}

{{-- Per-team progress + members table (top) --}}
@php $tach = $targetAchievement; @endphp
<div class="row g-3 mb-2">
    <div class="col-12">
        <h5 class="fw-semibold mb-1"><i class="bi bi-people-fill text-success"></i> Teams — progress</h5>
        <p class="text-muted small mb-0">{{ $tach['label'] }} · team monthly target vs revenue (approved &amp; completed) · members: all-time sales &amp; this month vs target</p>
    </div>
</div>
<div class="row g-3 mb-4">
    @forelse(($teamDashboardCards ?? collect()) as $tc)
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm team-progress-card h-100">
            <div class="card-header bg-white border-0 pb-0 pt-3">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <div class="fw-semibold">{{ $tc['name'] }}</div>
                        <div class="text-muted small">
                            @if(!empty($tc['company_name']))
                                {{ $tc['company_name'] }} ·
                            @endif
                            {{ $tc['members_count'] }} members
                        </div>
                    </div>
                    @if($tc['achievement_percent'] !== null)
                        <span class="badge rounded-pill bg-success">{{ $tc['achievement_percent'] }}%</span>
                    @else
                        <span class="badge rounded-pill bg-secondary">—</span>
                    @endif
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Team · this month (revenue / target)</span>
                    <span class="fw-semibold">${{ number_format($tc['monthly_revenue'], 0) }} <span class="text-muted fw-normal">/ ${{ number_format($tc['target'], 0) }}</span></span>
                </div>
                <div class="progress rounded-pill mb-3" style="height: 14px;">
                    <div class="progress-bar bg-success rounded-pill" role="progressbar"
                         style="width: {{ $tc['achievement_percent'] !== null ? min(100, $tc['achievement_percent']) : 0 }}%;"
                         aria-valuenow="{{ $tc['achievement_percent'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                @if(($tc['target'] ?? 0) <= 0)
                    <p class="text-muted small mb-3">No team target set for {{ $tc['month_label'] }}.</p>
                @endif

                @if(($tc['ppc_spending'] ?? 0) > 0)
                <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded bg-light border">
                    <i class="bi bi-graph-up text-danger"></i>
                    <span class="small text-muted">PPC Spending — {{ $tc['month_label'] }}</span>
                    <span class="ms-auto fw-bold text-danger">${{ number_format($tc['ppc_spending'], 2) }}</span>
                </div>
                @endif

                <div class="fw-semibold small mb-2"><i class="bi bi-person-lines-fill text-primary"></i> Members</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th class="text-center" title="Approved completed (all time)">Sales</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Month target</th>
                                <th class="text-end">Month revenue</th>
                                <th class="text-center" title="This month · vs personal target">Achieve</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tc['members'] ?? [] as $m)
                            <tr>
                                <td>
                                    {{ $m['name'] }}
                                    @if(!empty($m['is_head']))
                                        <span class="badge bg-warning text-dark ms-1" style="font-size:10px">Head</span>
                                    @endif
                                </td>
                                <td><span class="text-muted small">{{ $m['role_name'] }}</span></td>
                                <td class="text-center">{{ $m['sales_count'] }}</td>
                                <td class="text-end text-success">${{ number_format($m['sale_amount'], 0) }}</td>
                                <td class="text-end">${{ number_format($m['month_target'], 0) }}</td>
                                <td class="text-end">${{ number_format($m['month_revenue'], 0) }}</td>
                                <td class="text-center">
                                    @if($m['target_achievement_pct'] !== null)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">{{ $m['target_achievement_pct'] }}%</span>
                                    @elseif(($m['month_target'] ?? 0) <= 0)
                                        <span class="text-muted">—</span>
                                    @else
                                        <span class="text-muted">0%</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-3">No members in this team.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-light border mb-0 text-muted">No teams yet. Create teams under <strong>Teams</strong> to see progress here.</div>
    </div>
    @endforelse
</div>

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['label'=>'Total Users',       'value'=>$stats['total_users'],                          'icon'=>'people-fill',    'color'=>'primary'],
            ['label'=>'Companies',         'value'=>$stats['total_companies'],                      'icon'=>'buildings',      'color'=>'info'],
            ['label'=>'Teams',             'value'=>$stats['total_teams'],                          'icon'=>'people',         'color'=>'success'],
            ['label'=>'Total Sales',       'value'=>$stats['total_sales'],                          'icon'=>'cash-stack',     'color'=>'warning'],
            ['label'=>'Completed',         'value'=>$stats['sales_completed'],                      'icon'=>'check-circle',   'color'=>'success'],
            ['label'=>'Pending Approval',  'value'=>$stats['pending_approval'],                     'icon'=>'hourglass-split','color'=>'warning'],
            ['label'=>'Cancelled',         'value'=>$stats['sales_cancelled'],                      'icon'=>'x-circle',       'color'=>'danger'],
            ['label'=>'Total Revenue',     'value'=>'$'.number_format($stats['total_revenue'], 0),  'icon'=>'currency-dollar','color'=>'success'],
        ];
    @endphp
    @foreach($cards as $card)
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-{{ $card['color'] }} bg-opacity-10 p-3 flex-shrink-0">
                    <i class="bi bi-{{ $card['icon'] }} fs-5 text-{{ $card['color'] }}"></i>
                </div>
                <div>
                    <div class="text-muted small">{{ $card['label'] }}</div>
                    <div class="fw-bold fs-4">{{ $card['value'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Revenue trend + sales status --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-activity text-primary"></i> Revenue trend</h6>
                <span class="text-muted small">Last 30 days · approved &amp; completed sales</span>
            </div>
            <div class="card-body pt-2">
                <div class="dashboard-chart-wrap" style="height: 300px;">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-pie-chart-fill text-primary"></i> Sales by status</h6>
                <span class="text-muted small">Approved sales only</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center pt-2" style="min-height: 300px;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Companies bar + approval pipeline --}}
<div class="row g-3 mb-4">
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
    <div class="col-lg-5">
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

<div class="row g-3 mb-4">
    {{-- Company Breakdown table --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-buildings text-info"></i> Company breakdown
            </div>
            <div class="card-body ">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Logo</th>
                                <th>Company</th>
                                <th class="text-center">Teams</th>
                                <th class="text-center">Users</th>
                                <th class="text-center">Sales</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">{{ \DateTime::createFromFormat('!m', $month)->format('M') }} Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($companies as $company)
                            <tr>
                                <td>
                                    @if($company->logo)
                                        <img src="{{ asset('storage/' . $company->logo) }}" alt="{{ $company->name }}"
                                             style="height:36px;width:36px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                                    @else
                                        <div class="rounded bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center"
                                             style="height:36px;width:36px;border-radius:6px;">
                                            <i class="bi bi-buildings text-secondary small"></i>
                                        </div>
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $company->name }}</td>
                                <td class="text-center">{{ $company->teams_count }}</td>
                                <td class="text-center">{{ $company->users_count }}</td>
                                <td class="text-center">{{ $company->sales_count }}</td>
                                <td class="text-end text-success">${{ number_format($company->revenue, 0) }}</td>
                                <td class="text-end">${{ number_format($company->team_target, 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-muted text-center py-3">No companies yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Top Agents --}}
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-trophy text-warning"></i> Top Agents by Revenue
            </div>
            <div class="card-body ">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Agent</th>
                                <th>Team</th>
                                <th class="text-center">Sales</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topAgents as $i => $agent)
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td>
                                    <div>{{ $agent->name }}</div>
                                    <div class="text-muted small">{{ $agent->company?->name }}</div>
                                </td>
                                <td class="text-muted small">{{ $agent->team?->name ?? '-' }}</td>
                                <td class="text-center">{{ $agent->total_sales }}</td>
                                <td class="text-end fw-bold text-success">${{ number_format($agent->total_amount, 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-muted text-center py-3">No data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Sales --}}
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-cash-stack text-primary"></i> Recent Sales</span>
                <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body ">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Client</th>
                                <th>Agent</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSales as $sale)
                            @php $sc = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger']; @endphp
                            <tr>
                                <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                                <td class="text-muted small">{{ $sale->client_name }}</td>
                                <td class="text-muted small">{{ $sale->user?->name ?? '-' }}</td>
                                <td><span class="badge bg-{{ $sc[$sale->status] ?? 'secondary' }}">{{ ucfirst($sale->status) }}</span></td>
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
    const rt = document.getElementById('revenueTrendChart');
    if (rt) {
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
    if (sc) {
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
    if (cb) {
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
    if (ap) {
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
@endif
})();
</script>
@endsection
