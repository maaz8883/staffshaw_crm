@extends('admin.layout')

@section('title', 'Sales Performance')
@section('page-title', 'Sales Performance')
@section('page-icon', 'graph-up')

@section('content')

@php
    $monthLabel = \Carbon\Carbon::create()->month($month)->format('F') . ' ' . $year;
    // Targets carry forward: label one that comes from an earlier month
    $carriedFrom = fn ($t) => ($t && ((int) $t->month !== (int) $month || (int) $t->year !== (int) $year))
        ? \Carbon\Carbon::create((int) $t->year, (int) $t->month, 1)->format('M Y') : null;
    $carriedHint = fn ($t) => ($from = $carriedFrom($t))
        ? '<div class="form-text small text-info" title="Saving sets it from '.$monthLabel.' onwards"><i class="bi bi-arrow-repeat"></i> From '.$from.'</div>' : '';
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Today</div>
                <div class="fs-5 fw-semibold">${{ number_format($summary['today'] ?? 0, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">This Month</div>
                <div class="fs-5 fw-semibold">${{ number_format($summary['month'] ?? 0, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Total Target</div>
                <div class="fs-5 fw-semibold">${{ number_format($summary['target'] ?? 0, 2) }}</div>
            </div>
        </div>
    </div>
</div>

<form method="GET" action="{{ route('admin.targets.index') }}" class="d-flex gap-2 mb-3" id="targets-period-filter">
    <select name="month" class="form-select" style="max-width:160px" onchange="this.form.submit()">
        @for($m = 1; $m <= 12; $m++)
            <option value="{{ $m }}" @selected((int) $month === $m)>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
        @endfor
    </select>
    <select name="year" class="form-select" style="max-width:120px" onchange="this.form.submit()">
        @for($y = now()->year + 1; $y >= now()->year - 3; $y--)
            <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
        @endfor
    </select>
</form>

@forelse($teams as $team)
@php
    $canManageTeamTarget = $isAdmin || ($isTeamHead && (int) $team->team_head_id === (int) auth()->id());
    $canManageSubTeamTargets = $canManageTeamTarget;
    $canManageUserTargets = $canManageTeamTarget || $isSubTeamHead;

    $subTeamHeadUserIds = $team->subTeamHeads->pluck('user_id')->all();

    $agentRows = collect();
    foreach ($team->subTeamHeads as $subHead) {
        $subAgents = $team->users->filter(function ($u) use ($subHead) {
            return $u->role?->name === 'Agent'
                && ((int) $u->sub_team_head_id === (int) $subHead->id
                    || (int) $u->id === (int) $subHead->user_id);
        });
        foreach ($subAgents as $member) {
            if (! $isAdmin && ! $isTeamHead && ! $isSubTeamHead && (int) $member->id !== (int) auth()->id()) {
                continue;
            }
            $agentRows->push([
                'user' => $member,
                'seat' => (int) $member->id === (int) $subHead->user_id ? $subHead->title . ' Head' : $subHead->title,
                'is_head' => (int) $team->team_head_id === (int) $member->id,
            ]);
        }
    }

    $others = $team->users->filter(function ($u) use ($subTeamHeadUserIds) {
        return $u->role?->name === 'Agent'
            && $u->sub_team_head_id === null
            && ! in_array($u->id, $subTeamHeadUserIds, true);
    });

    if (
        $team->teamHead
        && $team->teamHead->role?->name === 'Agent'
        && ! $others->contains('id', $team->teamHead->id)
        && ! in_array($team->teamHead->id, $subTeamHeadUserIds, true)
        && ! $agentRows->contains(fn ($row) => (int) $row['user']->id === (int) $team->teamHead->id)
    ) {
        $others = $others->push($team->teamHead);
    }

    foreach ($others as $member) {
        if (! $isAdmin && ! $isTeamHead && ! $isSubTeamHead && (int) $member->id !== (int) auth()->id()) {
            continue;
        }
        if ($isSubTeamHead) {
            continue;
        }
        if ($agentRows->contains(fn ($row) => (int) $row['user']->id === (int) $member->id)) {
            continue;
        }
        $agentRows->push([
            'user' => $member,
            'seat' => (int) $team->team_head_id === (int) $member->id ? 'Team Lead' : 'Agent',
            'is_head' => (int) $team->team_head_id === (int) $member->id,
        ]);
    }

    $agentRows = $agentRows->sortBy(fn ($row) => $row['user']->name)->values();
    $teamTargetAmt = (float) ($team->currentTarget?->target_amount ?? 0);
    $teamAchieved = $agentRows->sum(fn ($row) => (float) ($row['user']->month_achieved ?? 0));
    $teamPct = $teamTargetAmt > 0 ? min(100, round(($teamAchieved / $teamTargetAmt) * 100, 1)) : 0;
@endphp

@if($canManageTeamTarget)
<form id="team-target-{{ $team->id }}" method="POST" action="{{ route('admin.targets.team', $team) }}" class="d-none">
    @csrf
    <input type="hidden" name="month" value="{{ $month }}">
    <input type="hidden" name="year" value="{{ $year }}">
</form>
@endif

@if($canManageUserTargets)
<form id="bulk-targets-{{ $team->id }}" method="POST" action="{{ route('admin.targets.user.bulk', $team) }}" class="d-none">
    @csrf
    <input type="hidden" name="month" value="{{ $month }}">
    <input type="hidden" name="year" value="{{ $year }}">
</form>
@endif

@foreach($team->subTeamHeads as $subHead)
    @if($canManageSubTeamTargets)
    <form id="sub-target-{{ $team->id }}-{{ $subHead->id }}" method="POST" action="{{ route('admin.targets.sub-team', $team) }}" class="d-none">
        @csrf
        <input type="hidden" name="sub_team_head_id" value="{{ $subHead->id }}">
        <input type="hidden" name="month" value="{{ $month }}">
        <input type="hidden" name="year" value="{{ $year }}">
    </form>
    @endif
@endforeach

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="fw-semibold">{{ $team->name }}</div>
                <div class="text-muted small">{{ $agentRows->count() }} sales agents · {{ $monthLabel }}</div>
            </div>
            @if($teamTargetAmt > 0)
                <div class="text-muted small text-end">
                    Achieved ${{ number_format($teamAchieved, 2) }} / ${{ number_format($teamTargetAmt, 2) }}
                    <div class="progress mt-1" style="height:8px;width:160px;margin-left:auto">
                        <div class="progress-bar {{ $teamPct >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $teamPct }}%"></div>
                    </div>
                </div>
            @endif
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Team target</th>
                        <th style="width:160px">Amount</th>
                        <th>Notes</th>
                        @if($canManageTeamTarget)<th style="width:100px"></th>@endif
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-muted">{{ $monthLabel }}</td>
                        @if($canManageTeamTarget)
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="target_amount" step="0.01" min="0" class="form-control"
                                        form="team-target-{{ $team->id }}"
                                        value="{{ $team->currentTarget?->target_amount ?? '' }}" placeholder="0.00" required>
                                </div>
                                {!! $carriedHint($team->currentTarget) !!}
                            </td>
                            <td>
                                <input type="text" name="notes" class="form-control form-control-sm"
                                    form="team-target-{{ $team->id }}"
                                    value="{{ $team->currentTarget?->notes ?? '' }}" placeholder="Optional">
                            </td>
                            <td>
                                <button type="submit" form="team-target-{{ $team->id }}" class="btn btn-sm btn-primary">Save</button>
                            </td>
                        @else
                            <td class="fw-semibold">${{ number_format($teamTargetAmt, 2) }}</td>
                            <td class="text-muted">{{ $team->currentTarget?->notes ?: '—' }}</td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>

        @if($team->subTeamHeads->isNotEmpty() && ($canManageSubTeamTargets || $team->subTeamHeads->contains(fn ($sh) => $sh->currentTarget)))
        <div class="table-responsive mb-3">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Sub-team</th>
                        <th>Head</th>
                        <th style="width:160px">Target</th>
                        <th>Notes</th>
                        @if($canManageSubTeamTargets)<th style="width:100px"></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($team->subTeamHeads as $subHead)
                        @if($canManageSubTeamTargets)
                        <tr>
                            <td class="fw-semibold">{{ $subHead->title }}</td>
                            <td>{{ $subHead->user?->name ?? '—' }}</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="target_amount" step="0.01" min="0" class="form-control"
                                        form="sub-target-{{ $team->id }}-{{ $subHead->id }}"
                                        value="{{ $subHead->currentTarget?->target_amount ?? '' }}" placeholder="0.00" required>
                                </div>
                                {!! $carriedHint($subHead->currentTarget) !!}
                            </td>
                            <td>
                                <input type="text" name="notes" class="form-control form-control-sm"
                                    form="sub-target-{{ $team->id }}-{{ $subHead->id }}"
                                    value="{{ $subHead->currentTarget?->notes ?? '' }}" placeholder="Optional">
                            </td>
                            <td>
                                <button type="submit" form="sub-target-{{ $team->id }}-{{ $subHead->id }}" class="btn btn-sm btn-primary">Save</button>
                            </td>
                        </tr>
                        @elseif($subHead->currentTarget)
                        <tr>
                            <td class="fw-semibold">{{ $subHead->title }}</td>
                            <td>{{ $subHead->user?->name ?? '—' }}</td>
                            <td>${{ number_format($subHead->currentTarget->target_amount, 2) }}</td>
                            <td class="text-muted">{{ $subHead->currentTarget->notes ?: '—' }}</td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Sales Agent</th>
                        <th>Seat</th>
                        <th>Target</th>
                        <th>Achieved</th>
                        <th>Progress</th>
                        @if($canManageUserTargets)
                            <th style="width:150px">Set target</th>
                            <th>Notes</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($agentRows as $row)
                        @php
                            $member = $row['user'];
                            $targetAmt = (float) ($member->currentTarget?->target_amount ?? 0);
                            $achievedAmt = (float) ($member->month_achieved ?? 0);
                            $pct = $targetAmt > 0 ? round(($achievedAmt / $targetAmt) * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td>
                                {{ $member->name }}
                                @if($row['is_head'])
                                    <span class="badge bg-secondary ms-1" style="font-size:10px">Lead</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $row['seat'] }}</td>
                            <td>${{ number_format($targetAmt, 2) }}</td>
                            <td>${{ number_format($achievedAmt, 2) }}</td>
                            <td style="min-width:140px">
                                <div class="progress" style="height:16px">
                                    <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : 'bg-danger' }}" style="width:{{ min(100, $pct) }}%">{{ $pct }}%</div>
                                </div>
                            </td>
                            @if($canManageUserTargets)
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                            name="targets[{{ $member->id }}][target_amount]"
                                            form="bulk-targets-{{ $team->id }}"
                                            value="{{ $member->currentTarget?->target_amount ?? '' }}"
                                            placeholder="0.00">
                                    </div>
                                    {!! $carriedHint($member->currentTarget) !!}
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm"
                                        name="targets[{{ $member->id }}][notes]"
                                        form="bulk-targets-{{ $team->id }}"
                                        value="{{ $member->currentTarget?->notes ?? '' }}"
                                        placeholder="Optional">
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManageUserTargets ? 7 : 5 }}" class="text-muted">No sales agents in this team.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($canManageUserTargets && $agentRows->isNotEmpty())
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" form="bulk-targets-{{ $team->id }}" class="btn btn-primary btn-sm">Save agent targets</button>
            </div>
        @endif
    </div>
</div>
@empty
<div class="card">
    <div class="card-body text-muted">
        @if($isAdmin)
            No teams found. <a href="{{ route('admin.teams.create') }}">Create a team</a> first.
        @elseif($isTeamHead)
            You are not assigned as team head for any team.
        @else
            You are not assigned to any team.
        @endif
    </div>
</div>
@endforelse

<p class="text-muted small mt-2">Achieved uses approved, completed sales for {{ $monthLabel }}. Set team, sub-team, and agent targets on this page.</p>

@endsection
