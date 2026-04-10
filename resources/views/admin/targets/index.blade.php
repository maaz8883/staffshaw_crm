@extends('admin.layout')

@section('title', 'Targets')
@section('page-title', 'Team & User Targets')
@section('page-icon', 'bullseye')

@section('content')

{{-- Month / Year Filter --}}
<form method="GET" action="{{ route('admin.targets.index') }}" class="d-flex gap-2 align-items-end mb-4">
    <div>
        <label class="form-label mb-1">Month</label>
        <select name="month" class="form-select form-select-sm">
            @foreach(range(1,12) as $m)
                <option value="{{ $m }}" @selected($m == $month)>
                    {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label mb-1">Year</label>
        <select name="year" class="form-select form-select-sm">
            @foreach(range(now()->year - 1, now()->year + 1) as $y)
                <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
</form>

@forelse($teams as $team)
@php
    // Can this logged-in user manage THIS team's targets?
    $canManageThisTeam = $isAdmin || $team->team_head_id === auth()->id();
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ $team->name }}</strong>
            <span class="text-muted ms-2 small">{{ $team->company?->name }}</span>
            @if($team->team_head_id === auth()->id())
                <span class="badge bg-warning text-dark ms-2">Your Team</span>
            @endif
        </div>
        <span class="badge bg-secondary">{{ $team->users->count() }} members</span>
    </div>

    <div class="card-body">

        {{-- ── Team Target ── --}}
        <div class="border rounded p-3 mb-4 bg-light">
            <h6 class="mb-3">
                <i class="bi bi-flag"></i>
                Team Target — {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}
            </h6>

            @if($canManageThisTeam)
            {{-- Team Head / Admin: editable form --}}
            <form method="POST" action="{{ route('admin.targets.team', $team) }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">

                <div class="col-md-4">
                    <label class="form-label mb-1">Target Amount</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" name="target_amount" step="0.01" min="0"
                            class="form-control"
                            value="{{ $team->currentTarget?->target_amount ?? '' }}"
                            placeholder="0.00" required>
                    </div>
                </div>

                <div class="col-md-5">
                    <label class="form-label mb-1">Notes</label>
                    <input type="text" name="notes" class="form-control form-control-sm"
                        value="{{ $team->currentTarget?->notes ?? '' }}"
                        placeholder="Optional notes">
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        {{ $team->currentTarget ? 'Update Team Target' : 'Set Team Target' }}
                    </button>
                </div>
            </form>

            @if($team->currentTarget)
                <div class="mt-2 small text-muted">
                    Current: <strong class="text-dark">${{ number_format($team->currentTarget->target_amount, 2) }}</strong>
                    @if($team->currentTarget->notes)
                        — {{ $team->currentTarget->notes }}
                    @endif
                </div>
            @endif

            @else
            {{-- Regular Agent: read-only --}}
            @if($team->currentTarget)
                <div class="d-flex align-items-center gap-3">
                    <span class="fs-5 fw-bold text-primary">${{ number_format($team->currentTarget->target_amount, 2) }}</span>
                    @if($team->currentTarget->notes)
                        <span class="text-muted small">{{ $team->currentTarget->notes }}</span>
                    @endif
                </div>
            @else
                <p class="text-muted mb-0 small">No team target set for this period.</p>
            @endif
            @endif
        </div>

        {{-- ── Individual User Targets ── --}}
        <h6 class="mb-3"><i class="bi bi-person-check"></i> Individual User Targets</h6>

        @forelse($team->users as $member)
        @php
            // Team head/admin: manage karo | Regular agent: sab dekho (read-only)
            $showRow = true;
        @endphp

        @if($showRow)
        <div class="border rounded p-3 mb-2 {{ $member->id === auth()->id() ? 'border-primary' : '' }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <strong>{{ $member->name }}</strong>
                    <span class="text-muted small ms-2">{{ $member->email }}</span>
                    @if($team->team_head_id === $member->id)
                        <span class="badge bg-warning text-dark ms-1">Team Head</span>
                    @endif
                    @if($member->id === auth()->id())
                        <span class="badge bg-primary ms-1">You</span>
                    @endif
                </div>
                @if($member->currentTarget)
                    <span class="badge bg-success fs-6">${{ number_format($member->currentTarget->target_amount, 2) }}</span>
                @else
                    <span class="badge bg-light text-muted">No target</span>
                @endif
            </div>

            @if($canManageThisTeam)
            {{-- Team Head / Admin: editable --}}
            <form method="POST" action="{{ route('admin.targets.user', $team) }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="user_id" value="{{ $member->id }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">

                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" name="target_amount" step="0.01" min="0"
                            class="form-control"
                            value="{{ $member->currentTarget?->target_amount ?? '' }}"
                            placeholder="0.00" required>
                    </div>
                </div>

                <div class="col-md-5">
                    <input type="text" name="notes" class="form-control form-control-sm"
                        value="{{ $member->currentTarget?->notes ?? '' }}"
                        placeholder="Notes (optional)">
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                        {{ $member->currentTarget ? 'Update' : 'Set Target' }}
                    </button>
                </div>
            </form>

            @else
            {{-- Regular agent: read-only own target --}}
            @if($member->currentTarget?->notes)
                <small class="text-muted">{{ $member->currentTarget->notes }}</small>
            @endif
            @endif
        </div>
        @endif
        @empty
            <p class="text-muted mb-0">No users in this team yet.</p>
        @endforelse

        {{-- Progress bar (only if team target set and user is manager/admin/head) --}}
        @if($team->currentTarget && $canManageThisTeam)
            @php
                $totalUserTargets = $team->users->sum(fn($u) => $u->currentTarget?->target_amount ?? 0);
                $teamTarget       = $team->currentTarget->target_amount;
                $pct              = $teamTarget > 0 ? min(100, round(($totalUserTargets / $teamTarget) * 100)) : 0;
            @endphp
            <div class="mt-3 pt-3 border-top">
                <div class="d-flex justify-content-between small mb-1">
                    <span>Allocated to members: <strong>${{ number_format($totalUserTargets, 2) }}</strong></span>
                    <span>Team target: <strong>${{ number_format($teamTarget, 2) }}</strong></span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : ($pct >= 60 ? 'bg-warning' : 'bg-danger') }}"
                        style="width: {{ $pct }}%"></div>
                </div>
                <small class="text-muted">{{ $pct }}% of team target distributed</small>
            </div>
        @endif

    </div>
</div>
@empty
    <div class="alert alert-info">
        @if($isAdmin)
            No teams found. <a href="{{ route('admin.teams.create') }}">Create a team</a> first.
        @elseif($isTeamHead)
            You are not assigned as team head for any team.
        @else
            You are not assigned to any team.
        @endif
    </div>
@endforelse

@endsection
