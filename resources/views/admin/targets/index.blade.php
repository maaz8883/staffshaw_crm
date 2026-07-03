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
    // Team target: admin or the team's own team head
    $canManageTeamTarget = $isAdmin ||
        ($isTeamHead && (int)$team->team_head_id === (int)auth()->id());

    // Sub-team targets: admin or team head only (NOT sub-team heads)
    $canManageSubTeamTargets =
        $isAdmin ||
        ($isTeamHead && (int)$team->team_head_id === (int)auth()->id());

    // User targets: admin, team head, or sub-team head
    $canManageUserTargets =
        $isAdmin ||
        ($isTeamHead && (int)$team->team_head_id === (int)auth()->id()) ||
        $isSubTeamHead;
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ $team->name }}</strong>
            <span class="text-muted ms-2 small">{{ $team->company?->name }}</span>
            @if((int)$team->team_head_id === (int)auth()->id())
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

            @if($canManageTeamTarget)
            {{-- Admin only: editable form --}}
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

        {{-- ── Sub-Team Heads with their Users ── --}}
        @if($team->subTeamHeads->isNotEmpty())
        <h6 class="mb-3"><i class="bi bi-people-fill"></i> Sub-Team Heads & Their Members</h6>
        
        @foreach($team->subTeamHeads as $subHead)
        @php
            // Get users under this sub-team head
            // Include both: users with matching sub_team_head_id AND the sub-team head themselves
            $subTeamUsers = $team->users->filter(function($u) use ($subHead) {
                return (int) $u->sub_team_head_id === (int) $subHead->id
                    || (int) $u->id === (int) $subHead->user_id;
            });
            $totalMembers = $subTeamUsers->count();
            
            // Debug output (remove after testing)
            if (config('app.debug')) {
                \Log::info('Target Page - Sub-Team Debug', [
                    'sub_head_id' => $subHead->id,
                    'sub_head_title' => $subHead->title,
                    'sub_head_user_id' => $subHead->user_id,
                    'total_team_users' => $team->users->count(),
                    'filtered_users_count' => $totalMembers,
                    'filtered_user_ids' => $subTeamUsers->pluck('id')->toArray(),
                    'filtered_user_names' => $subTeamUsers->pluck('name')->toArray(),
                ]);
            }
        @endphp
        
        <div class="border rounded p-3 mb-3 bg-light">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <strong class="fs-5">{{ $subHead->title }}</strong>
                    <span class="text-muted small ms-2">(Sub-Team Head: {{ $subHead->user->name }})</span>
                </div>
                <span class="badge bg-info">{{ $totalMembers }} members</span>
            </div>

            {{-- Sub-Team Target Form --}}
            @if($canManageSubTeamTargets)
            <div class="border rounded p-3 mb-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h6 class="mb-2 text-white"><i class="bi bi-flag-fill"></i> Sub-Team Target</h6>
                <form method="POST" action="{{ route('admin.targets.sub-team', $team) }}" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="sub_team_head_id" value="{{ $subHead->id }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year" value="{{ $year }}">

                    <div class="col-md-4">
                        <label class="form-label mb-1 small text-white">Target Amount</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" name="target_amount" step="0.01" min="0"
                                class="form-control"
                                value="{{ $subHead->currentTarget?->target_amount ?? '' }}"
                                placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label mb-1 small text-white">Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm"
                            value="{{ $subHead->currentTarget?->notes ?? '' }}"
                            placeholder="Optional notes">
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-light btn-sm w-100">
                            {{ $subHead->currentTarget ? 'Update' : 'Set' }} Sub-Team Target
                        </button>
                    </div>
                </form>

                @if($subHead->currentTarget)
                    <div class="mt-2 small text-white">
                        Current: <strong class="text-white" style="text-shadow: 0 0 10px rgba(255,255,255,0.5);">${{ number_format($subHead->currentTarget->target_amount, 2) }}</strong>
                        @if($subHead->currentTarget->notes)
                            — {{ $subHead->currentTarget->notes }}
                        @endif
                    </div>
                @endif
            </div>
            @else
            {{-- Read-only for non-managers --}}
            @if($subHead->currentTarget)
                <div class="border rounded p-3 mb-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h6 class="mb-2 text-white"><i class="bi bi-flag-fill"></i> Sub-Team Target</h6>
                    <div class="d-flex align-items-center gap-3">
                        <span class="fs-5 fw-bold text-white" style="text-shadow: 0 0 10px rgba(255,255,255,0.5);">${{ number_format($subHead->currentTarget->target_amount, 2) }}</span>
                        @if($subHead->currentTarget->notes)
                            <span class="small text-white">{{ $subHead->currentTarget->notes }}</span>
                        @endif
                    </div>
                </div>
            @endif
            @endif

            @if($subTeamUsers->isNotEmpty())
                @foreach($subTeamUsers as $member)
                @php
                    $showRow = $isAdmin || $isTeamHead || $isSubTeamHead || (int)$member->id === (int)auth()->id();
                @endphp

                @if($showRow)
                <div class="border rounded p-3 mb-2 bg-white {{ (int)$member->id === (int)auth()->id() ? 'border-primary' : '' }}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong>{{ $member->name }}</strong>
                            <span class="text-muted small ms-2">{{ $member->email }}</span>
                            @if($member->id === $subHead->user_id)
                                <span class="badge bg-info text-dark ms-1">Sub-Team Head</span>
                            @endif
                            @if((int)$member->id === (int)auth()->id())
                                <span class="badge bg-primary ms-1">You</span>
                            @endif
                        </div>
                        @if($member->currentTarget)
                            <span class="badge bg-success fs-6">${{ number_format($member->currentTarget->target_amount, 2) }}</span>
                        @else
                            <span class="badge bg-light text-muted">No target</span>
                        @endif
                    </div>

                    @if($canManageUserTargets)
                    {{-- Admin / Team Head: editable --}}
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
                    {{-- Team Head & Agent: read-only --}}
                    @if($member->currentTarget?->notes)
                        <small class="text-muted">{{ $member->currentTarget->notes }}</small>
                    @endif
                    @endif
                </div>
                @endif
                @endforeach
            @else
                <p class="text-muted mb-0 small">No members assigned to this sub-team yet.</p>
            @endif

            {{-- Sub-Team Progress bar --}}
            @if($subHead->currentTarget && $canManageUserTargets)
                @php
                    $totalSubTeamUserTargets = $subTeamUsers->sum(fn($u) => $u->currentTarget?->target_amount ?? 0);
                    $subTeamTarget = $subHead->currentTarget->target_amount;
                    $subPct = $subTeamTarget > 0 ? min(100, round(($totalSubTeamUserTargets / $subTeamTarget) * 100)) : 0;
                @endphp
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Allocated to members: <strong>${{ number_format($totalSubTeamUserTargets, 2) }}</strong></span>
                        <span>Sub-team target: <strong>${{ number_format($subTeamTarget, 2) }}</strong></span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar {{ $subPct >= 100 ? 'bg-success' : ($subPct >= 60 ? 'bg-warning' : 'bg-danger') }}"
                            style="width: {{ $subPct }}%"></div>
                    </div>
                    <small class="text-muted">{{ $subPct }}% of sub-team target distributed</small>
                </div>
            @endif
        </div>
        @endforeach
        @endif

        {{-- ── Users without Sub-Team Head (including Team Head, but excluding Sub-Team Heads) ── --}}
        @php
            $subTeamHeadUserIds = $team->subTeamHeads->pluck('user_id')->toArray();
            $usersWithoutSubHead = $team->users->filter(function($u) use ($subTeamHeadUserIds) {
                return $u->sub_team_head_id === null && !in_array($u->id, $subTeamHeadUserIds);
            });

            // Team Head may not have their own team_id pointing at this team,
            // so they can be missing from $team->users. Ensure they still show up here.
            if ($team->teamHead && ! $usersWithoutSubHead->contains('id', $team->teamHead->id)) {
                $team->teamHead->currentTarget = \App\Models\UserTarget::where([
                    'user_id' => $team->teamHead->id,
                    'team_id' => $team->id,
                    'month'   => $month,
                    'year'    => $year,
                ])->first();
                $usersWithoutSubHead = $usersWithoutSubHead->push($team->teamHead);
            }

            // Project Managers joined via Team_Membership are not linked via users.team_id either.
            foreach ($team->approvedProjectManagers as $pm) {
                if (! $usersWithoutSubHead->contains('id', $pm->id)) {
                    $usersWithoutSubHead = $usersWithoutSubHead->push($pm);
                }
            }
        @endphp

        @if($usersWithoutSubHead->isNotEmpty() && !$isSubTeamHead)
        <h6 class="mb-3"><i class="bi bi-person-check"></i> Other Team Members</h6>

        @foreach($usersWithoutSubHead as $member)
        @php
            $showRow = $isAdmin || $isTeamHead || $isSubTeamHead || (int)$member->id === (int)auth()->id();
        @endphp

        @if($showRow)
        <div class="border rounded p-3 mb-2 {{ (int)$member->id === (int)auth()->id() ? 'border-primary' : '' }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <strong>{{ $member->name }}</strong>
                    <span class="text-muted small ms-2">{{ $member->email }}</span>
                    @if((int)$team->team_head_id === (int)$member->id)
                        <span class="badge bg-warning text-dark ms-1">Team Head</span>
                    @endif
                    @if($member->role?->name === 'Project Manager')
                        <span class="badge bg-info text-dark ms-1">Project Manager</span>
                    @endif
                    @if((int)$member->id === (int)auth()->id())
                        <span class="badge bg-primary ms-1">You</span>
                    @endif
                </div>
                @if($member->currentTarget)
                    <span class="badge bg-success fs-6">${{ number_format($member->currentTarget->target_amount, 2) }}</span>
                @else
                    <span class="badge bg-light text-muted">No target</span>
                @endif
            </div>

            @if($canManageUserTargets)
            {{-- Admin / Team Head: editable --}}
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
            {{-- Team Head & Agent: read-only --}}
            @if($member->currentTarget?->notes)
                <small class="text-muted">{{ $member->currentTarget->notes }}</small>
            @endif
            @endif
        </div>
        @endif
        @endforeach
        @endif

        @if($team->users->isEmpty())
            <p class="text-muted mb-0">No users in this team yet.</p>
        @endif

        {{-- Progress bar (only for Admin and Team Head, not for Sub-Team Heads) --}}
        @if($team->currentTarget && $canManageUserTargets && !$isSubTeamHead)
            @php
                $totalSubTeamTargets = $team->subTeamHeads->sum(fn($sh) => $sh->currentTarget?->target_amount ?? 0);
                $totalOtherMembersTargets = $usersWithoutSubHead->sum(fn($u) => $u->currentTarget?->target_amount ?? 0);
                $totalAllocated = $totalSubTeamTargets + $totalOtherMembersTargets;
                $teamTarget = $team->currentTarget->target_amount;
                $pct = $teamTarget > 0 ? min(100, round(($totalAllocated / $teamTarget) * 100)) : 0;
            @endphp
            <div class="mt-3 pt-3 border-top">
                <div class="d-flex justify-content-between small mb-1">
                    <span>
                        Allocated: 
                        <strong>${{ number_format($totalAllocated, 2) }}</strong>
                        <span class="text-muted">
                            (Sub-teams: ${{ number_format($totalSubTeamTargets, 2) }} + Others: ${{ number_format($totalOtherMembersTargets, 2) }})
                        </span>
                    </span>
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
