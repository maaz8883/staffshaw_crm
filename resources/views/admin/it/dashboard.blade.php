@extends('admin.layout')

@section('title', 'Dashboard')
@section('page-title', auth()->user()->hasRole('HR') ? 'HR Dashboard' : 'IT Dashboard')
@section('page-icon', 'shield-lock')

@section('content')
@php $hr = auth()->user()->hasRole('HR'); @endphp
<style>
    .it-tile .num { font-size: 1.9rem; font-weight: 700; line-height: 1.1; }
    .it-tile a { color: inherit; text-decoration: none; }
    .it-list li { font-size: 13px; padding: 7px 0; border-bottom: 1px solid var(--border-color, rgba(0,0,0,.08)); }
    .it-list li:last-child { border-bottom: 0; }
    .it-list .when { font-size: 11px; opacity: .65; }
    .it-chip { display: inline-block; font-size: 12px; padding: 2px 9px; border-radius: 999px; border: 1px solid var(--border-color, rgba(0,0,0,.15)); margin: 0 4px 6px 0; }
</style>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> New user</a>
    @if($hr)
    <a href="{{ route('admin.expenses.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-wallet2"></i> Daily expenses</a>
    @else
    <a href="{{ route('admin.brands.create') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-building-add"></i> New brand</a>
    @endif
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Total users', $stats['total'], route('admin.users.index'), ''],
        ['Active', $stats['active'], route('admin.users.index'), 'text-success'],
        ['Pending approval', $stats['pending'], $hr ? route('admin.users.index', ['status' => 'pending']) : route('admin.pending-registrations.index'), 'text-warning'],
        ['Banned', $stats['banned'], route('admin.users.index'), 'text-danger'],
        $hr ? ['Expenses today', \App\Models\Expense::money($stats['expenses_today']), route('admin.expenses.index'), 'text-danger']
            : ['Brands', $stats['brands'], route('admin.brands.index'), ''],
    ] as [$label, $value, $url, $class])
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm h-100 it-tile"><div class="card-body">
                <a href="{{ $url }}"><div class="text-muted small">{{ $label }}</div><div class="num {{ $class }}">{{ is_numeric($value) ? number_format($value) : $value }}</div></a>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Users by role</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    @foreach($roles as $role)
                        <tr><td>{{ $role->name }}</td><td class="text-end fw-semibold">{{ $role->users_count }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
    @if($hr)
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold d-flex justify-content-between"><span>Expenses this month</span><span class="text-danger">{{ \App\Models\Expense::money($stats['expenses_month']) }}</span></div>
            <div class="card-body">
                @forelse($recentExpenses as $e)
                    <div class="d-flex justify-content-between small py-1 border-bottom">
                        <span><a href="{{ route('admin.expenses.index', ['date' => $e->expense_date->toDateString()]) }}">{{ $e->expense_date->format('d M') }}</a> · <strong>{{ $e->category }}</strong> {{ $e->note ? '— '.$e->note : '' }}</span>
                        <span class="fw-semibold">{{ \App\Models\Expense::money($e->amount) }}</span>
                    </div>
                @empty
                    <div class="text-muted small">No expenses this month yet.</div>
                @endforelse
            </div>
        </div>
    </div>
    @else
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Brand setup</div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="small fw-semibold mb-1">Not assigned to any team <span class="text-muted fw-normal">({{ $brandsWithoutTeam->count() }}) — agents can't pick these on sales</span></div>
                    @forelse($brandsWithoutTeam as $b)
                        <a href="{{ route(auth()->user()->hasRole('Admin') ? 'admin.brands.edit' : 'admin.brands.show', $b) }}" class="it-chip text-decoration-none">{{ $b->name }}</a>
                    @empty
                        <div class="text-muted small">Every brand belongs to a team.</div>
                    @endforelse
                    @if($brandsWithoutTeam->isNotEmpty())
                        <div class="text-muted small">Assign brands from Teams → Edit (Admin / Manager).</div>
                    @endif
                </div>
                <div>
                    <div class="small fw-semibold mb-1">Missing logo <span class="text-muted fw-normal">({{ $brandsMissingLogo->count() }}) — shown on invoices</span></div>
                    @forelse($brandsMissingLogo as $b)
                        <a href="{{ route(auth()->user()->hasRole('Admin') ? 'admin.brands.edit' : 'admin.brands.show', $b) }}" class="it-chip text-decoration-none">{{ $b->name }}</a>
                    @empty
                        <div class="text-muted small">Every brand has a logo.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Recent sign-ins</div>
            <div class="card-body">
                @if($recentLogins->isEmpty())
                    <div class="text-muted small">No sign-ins recorded yet.</div>
                @else
                    <ul class="list-unstyled mb-0 it-list">
                        @foreach($recentLogins as $log)
                            <li class="d-flex justify-content-between gap-2">
                                <span><strong>{{ $log->user?->name ?? 'Deleted user' }}</strong> <span class="text-muted">{{ $log->user?->email }}</span></span>
                                <span class="when text-end text-nowrap">{{ $log->created_at->diffForHumans() }}@if($log->ip_address)<br>{{ $log->ip_address }}@if($log->city) · {{ $log->city }}@endif @endif</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold d-flex justify-content-between">
                <span>Account changes</span>
                @unless($hr)<a href="{{ route('admin.activity-logs.index') }}" class="small">All activity</a>@endunless
            </div>
            <div class="card-body">
                @if($accountChanges->isEmpty())
                    <div class="text-muted small">No account changes yet.</div>
                @else
                    <ul class="list-unstyled mb-0 it-list">
                        @foreach($accountChanges as $log)
                            <li>
                                <strong>{{ $log->user?->name ?? 'System' }}</strong> — {{ $log->description }}
                                <div class="when">{{ $log->created_at->diffForHumans() }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
