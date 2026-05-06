@extends('admin.layout')

@section('title', 'Team Details')
@section('page-title', 'Team Details')
@section('page-icon', 'people-fill')

@section('content')

{{-- Team Info --}}
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-1">{{ $team->name }}</h5>
        <div class="text-muted mb-2">{{ $team->company?->name ?? 'No company' }}</div>
        <div class="mb-2">
            <small class="text-muted">Team Head:</small>
            <strong>{{ $team->teamHead?->name ?? 'Not assigned' }}</strong>
        </div>
        <p class="mb-0">{{ $team->description ?: 'No description provided.' }}</p>
    </div>
</div>

{{-- Team Hierarchy --}}
@if(!auth()->user()->hasRole('PPC'))
<div class="card mb-3">
    <div class="card-header bg-primary text-white">
        <i class="bi bi-diagram-3-fill"></i> Team Hierarchy
    </div>
    <div class="card-body">
        {{-- Team Head --}}
        @if($team->teamHead)
        <div class="mb-4">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-person-badge-fill text-primary fs-5 me-2"></i>
                <div>
                    <div class="fw-bold">{{ $team->teamHead->name }}</div>
                    <small class="text-muted">Team Head</small>
                    @if($team->teamHead->email)
                        <small class="text-muted"> • {{ $team->teamHead->email }}</small>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Sub-Team Heads --}}
        @if($team->subTeamHeads->count() > 0)
        <div class="mb-3">
            <h6 class="text-muted mb-3"><i class="bi bi-people-fill"></i> Sub-Team Heads</h6>
            
            @foreach($team->subTeamHeads as $subHead)
            <div class="mb-4 ps-4 border-start border-3 border-primary">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-person-fill text-info fs-5 me-2"></i>
                    <div>
                        <div class="fw-bold">{{ $subHead->user->name }}</div>
                        <small class="text-muted">{{ $subHead->title }}</small>
                        @if($subHead->user->email)
                            <small class="text-muted"> • {{ $subHead->user->email }}</small>
                        @endif
                    </div>
                </div>

                {{-- Users under this Sub-Team Head --}}
                @php
                    $usersUnderSubHead = $team->users->where('sub_team_head_id', $subHead->id);
                @endphp
                @if($usersUnderSubHead->count() > 0)
                <div class="mt-2 ps-4 border-start border-2 border-secondary">
                    <div class="small text-muted mb-2">Team Members:</div>
                    <ul class="list-unstyled mb-0">
                        @foreach($usersUnderSubHead as $user)
                        <li class="mb-2">
                            <i class="bi bi-arrow-return-right text-secondary"></i>
                            <strong>{{ $user->name }}</strong>
                            @if($user->email)
                                <span class="text-muted small">({{ $user->email }})</span>
                            @endif
                            @if($user->role)
                                <span class="badge bg-secondary">{{ $user->role->name }}</span>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        {{-- Users without Sub-Team Head --}}
        @php
            $usersWithoutSubHead = $team->users->whereNull('sub_team_head_id');
        @endphp
        @if($usersWithoutSubHead->count() > 0)
        <div class="mt-4 ps-4 border-start border-3 border-secondary">
            <h6 class="text-muted mb-3"><i class="bi bi-people"></i> Other Team Members</h6>
            <ul class="list-unstyled mb-0">
                @foreach($usersWithoutSubHead as $user)
                <li class="mb-2">
                    <i class="bi bi-person text-secondary"></i>
                    <strong>{{ $user->name }}</strong>
                    @if($user->email)
                        <span class="text-muted small">({{ $user->email }})</span>
                    @endif
                    @if($user->role)
                        <span class="badge bg-secondary">{{ $user->role->name }}</span>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @if($team->users->count() === 0)
        <div class="text-muted text-center py-3">
            <i class="bi bi-inbox"></i> No users assigned to this team yet.
        </div>
        @endif
    </div>
</div>
@endif

{{-- PPC Spending Section --}}
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-graph-up"></i> PPC Spending</span>
        <span class="badge bg-danger fs-6">${{ number_format($totalSpending, 2) }}</span>
    </div>
    <div class="card-body">

        {{-- Month/Year filter --}}
        <form method="GET" action="{{ route('admin.teams.show', $team) }}" class="d-flex gap-2 align-items-end mb-4">
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

        {{-- Add form — PPC user only --}}
        @if($isPpc)
        <form method="POST" action="{{ route('admin.ppc.store') }}"
              class="row g-3 align-items-end mb-4 p-3 bg-light rounded border">
            @csrf
            <input type="hidden" name="team_id_override" value="{{ $team->id }}">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="year" value="{{ $year }}">

            <div class="col-md-4">
                <label class="form-label">Amount ($) <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="amount" class="form-control"
                        step="0.01" min="0.01" placeholder="0.00" required>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control"
                    placeholder="e.g. Google Ads, Facebook Ads...">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Add</button>
            </div>
        </form>
        @endif

        {{-- History table --}}
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Added By</th>
                        <th>Notes</th>
                        <th class="text-end">Amount</th>
                        @if(auth()->user()->hasRole('Admin'))<th class="text-end">Action</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($spendings as $entry)
                    <tr>
                        <td class="text-muted small">{{ $entry->created_at->format('d M Y') }}</td>
                        <td>{{ $entry->user?->name ?? '-' }}</td>
                        <td class="text-muted small">{{ $entry->notes ?: '-' }}</td>
                        <td class="text-end fw-semibold text-danger">${{ number_format($entry->amount, 2) }}</td>
                        @if(auth()->user()->hasRole('Admin'))
                        <td class="text-end">
                            <form method="POST"
                                action="{{ route('admin.ppc.destroy', $entry) }}"
                                class="d-inline js-admin-delete-form"
                                data-swal-title="Delete this entry?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                        @endif                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-muted text-center py-3">
                            No spending entries for {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($spendings->count() > 0)
                <tfoot class="table-light">
                    <tr>
                        <td colspan="{{ auth()->user()->hasRole('Admin') ? 3 : 3 }}" class="fw-semibold text-end">Total</td>
                        <td class="text-end fw-bold text-danger">${{ number_format($totalSpending, 2) }}</td>
                        @if(auth()->user()->hasRole('Admin'))<td></td>@endif
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@endsection
