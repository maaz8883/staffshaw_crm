@extends('admin.layout')
@section('title', 'Daily Expenses')
@section('page-title', 'Daily Expenses')
@section('page-icon', 'wallet2')
@section('content')
@php
    $m = fn ($v) => \App\Models\Expense::money($v); // rupees
    $isToday = $day->isToday();
    $prev = $day->copy()->subDay()->toDateString();
    $next = $day->copy()->addDay()->toDateString();
@endphp
<style>
    .exp-tile .num { font-size: 1.6rem; font-weight: 700; line-height: 1.15; }
    .exp-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
    .exp-days .head { font-size: 11px; text-transform: uppercase; opacity: .6; text-align: center; }
    .exp-days a { display: block; border: 1px solid var(--border-color, rgba(0,0,0,.1)); border-radius: 8px; padding: 6px 7px; text-decoration: none; color: inherit; min-height: 54px; }
    .exp-days a:hover { border-color: #579dff; }
    .exp-days a.has { background: rgba(87, 157, 255, .08); }
    .exp-days a.sel { border-color: #579dff; box-shadow: 0 0 0 2px rgba(87, 157, 255, .25); }
    .exp-days a.future { opacity: .45; }
    .exp-days .d { font-size: 12px; font-weight: 600; }
    .exp-days .t { font-size: 11px; font-weight: 700; color: #b42318; }
</style>

{{-- Day picker --}}
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <a href="{{ route('admin.expenses.index', ['date' => $prev]) }}" class="btn btn-sm btn-outline-secondary" title="Previous day"><i class="bi bi-chevron-left"></i></a>
        <input type="date" name="date" value="{{ $day->toDateString() }}" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
        <a href="{{ route('admin.expenses.index', ['date' => $next]) }}" class="btn btn-sm btn-outline-secondary" title="Next day"><i class="bi bi-chevron-right"></i></a>
        @unless($isToday)
            <a href="{{ route('admin.expenses.index') }}" class="btn btn-sm btn-link">Today</a>
        @endunless
    </form>
    <div class="fw-semibold">{{ $day->format('l, d M Y') }} @if($isToday)<span class="badge bg-primary ms-1">Today</span>@endif</div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100 exp-tile"><div class="card-body">
        <div class="text-muted small">{{ $isToday ? 'Spent today' : 'Spent on '.$day->format('d M') }}</div>
        <div class="num text-danger">{{ $m($dayTotal) }}</div>
        <div class="small text-muted">{{ $dayExpenses->count() }} {{ $dayExpenses->count() === 1 ? 'entry' : 'entries' }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100 exp-tile"><div class="card-body">
        <div class="text-muted small">{{ $day->format('F Y') }} so far</div>
        <div class="num">{{ $m($monthTotal) }}</div>
        <div class="small text-muted">{{ $monthDays->count() }} {{ $monthDays->count() === 1 ? 'day' : 'days' }} with expenses</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100 exp-tile"><div class="card-body">
        <div class="text-muted small">Daily average ({{ $day->format('M') }})</div>
        <div class="num">{{ $m($monthDays->count() ? $monthTotal / $monthDays->count() : 0) }}</div>
        <div class="small text-muted">Per day with expenses</div>
    </div></div></div>
</div>

<div class="row g-3 mb-4">
    {{-- Quick add for the selected day --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Add expense — {{ $day->format('d M Y') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.expenses.store') }}">
                    @csrf
                    <input type="hidden" name="expense_date" value="{{ $day->toDateString() }}">
                    <div class="mb-2">
                        <label class="form-label small mb-1">Category <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control" list="expense-categories" required placeholder="e.g. Food & Refreshments" value="{{ old('category') }}">
                        <datalist id="expense-categories">
                            @foreach(\App\Models\Expense::CATEGORIES as $category)
                                <option value="{{ $category }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Amount (Rs) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="amount" class="form-control" required value="{{ old('amount') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Note</label>
                        <input type="text" name="note" class="form-control" placeholder="What was it for?" value="{{ old('note') }}">
                    </div>
                    <details class="mb-3">
                        <summary class="small text-muted">More: client, brand, billable</summary>
                        <div class="mt-2">
                            <select name="client_id" class="form-select form-select-sm mb-2 js-select2">
                                <option value="">No client</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                            <select name="brand_id" class="form-select form-select-sm mb-2 js-select2">
                                <option value="">No brand</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected((string) old('brand_id') === (string) $brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_billable" value="1" id="is_billable" @checked(old('is_billable'))>
                                <label class="form-check-label small" for="is_billable">Billable to the client</label>
                            </div>
                        </div>
                    </details>
                    <button class="btn btn-primary w-100"><i class="bi bi-plus-circle"></i> Add expense</button>
                </form>
            </div>
        </div>
    </div>

    {{-- That day's entries --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold d-flex justify-content-between">
                <span>Expenses on {{ $day->format('d M Y') }}</span>
                <span class="text-danger">{{ $m($dayTotal) }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Category</th><th>Note</th><th>Added by</th><th class="text-end">Amount</th><th></th></tr></thead>
                        <tbody>
                        @forelse($dayExpenses as $expense)
                            <tr>
                                <td class="fw-semibold">{{ $expense->category }}
                                    @if($expense->is_billable)<span class="badge bg-info-subtle text-info-emphasis ms-1">Billable</span>@endif
                                </td>
                                <td class="small">{{ $expense->note ?: '—' }}
                                    @if($expense->client || $expense->brand)
                                        <div class="text-muted">{{ collect([$expense->client?->name, $expense->brand?->name])->filter()->implode(' · ') }}</div>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $expense->creator?->name ?? '—' }}<div>{{ $expense->created_at?->format('h:i A') }}</div></td>
                                <td class="text-end fw-semibold">{{ $m($expense->amount) }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('admin.expenses.edit', $expense) }}" class="btn btn-sm btn-outline-warning">Edit</a>
                                    @if($canDelete)
                                        <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this expense?">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-4">No expenses on this day yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Month at a glance: total per day --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold d-flex justify-content-between">
        <span>{{ $day->format('F Y') }} — day by day</span>
        <span>{{ $m($monthTotal) }}</span>
    </div>
    <div class="card-body">
        <div class="exp-days">
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $w)<div class="head">{{ $w }}</div>@endforeach
            @for($i = 1; $i < $day->copy()->startOfMonth()->dayOfWeekIso; $i++)<div></div>@endfor
            @for($d = $day->copy()->startOfMonth(); $d->lte($day->copy()->endOfMonth()); $d->addDay())
                @php $row = $monthDays->get($d->toDateString()); @endphp
                <a href="{{ route('admin.expenses.index', ['date' => $d->toDateString()]) }}"
                   class="{{ $row ? 'has' : '' }} {{ $d->isSameDay($day) ? 'sel' : '' }} {{ $d->isFuture() ? 'future' : '' }}">
                    <div class="d">{{ $d->day }}</div>
                    @if($row)<div class="t">{{ $m($row->total) }}</div>@endif
                </a>
            @endfor
        </div>
    </div>
</div>

@if($all)
{{-- Every expense (Admin / Finance) --}}
<div class="card border-0 shadow-sm mb-4" id="all-expenses">
    <div class="card-header bg-transparent fw-semibold d-flex flex-wrap justify-content-between gap-2">
        <span>All expenses <span class="text-muted fw-normal">({{ number_format($all->total()) }} {{ $all->total() === 1 ? 'entry' : 'entries' }}{{ $allFiltered ? ', filtered' : '' }})</span></span>
        <span class="text-danger">{{ $m($allTotal) }}</span>
    </div>
    <div class="card-body pb-0">
        <form method="GET" action="{{ route('admin.expenses.index') }}#all-expenses" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="date" value="{{ $day->toDateString() }}">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    @foreach($allCategories as $c)
                        <option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Search</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Note or category">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill">Filter</button>
                @if($allFiltered)
                    <a href="{{ route('admin.expenses.index', ['date' => $day->toDateString()]) }}#all-expenses" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light"><tr><th>Date</th><th>Category</th><th>Note</th><th>Added by</th><th class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse($all as $expense)
                <tr>
                    <td class="text-nowrap"><a href="{{ route('admin.expenses.index', ['date' => $expense->expense_date->toDateString()]) }}">{{ $expense->expense_date->format('d M Y') }}</a></td>
                    <td class="fw-semibold">{{ $expense->category }}
                        @if($expense->is_billable)<span class="badge bg-info-subtle text-info-emphasis ms-1">Billable</span>@endif
                    </td>
                    <td class="small">{{ $expense->note ?: '—' }}
                        @if($expense->client || $expense->brand)
                            <div class="text-muted">{{ collect([$expense->client?->name, $expense->brand?->name])->filter()->implode(' · ') }}</div>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $expense->creator?->name ?? '—' }}</td>
                    <td class="text-end fw-semibold">{{ $m($expense->amount) }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.expenses.edit', $expense) }}" class="btn btn-sm btn-outline-warning">Edit</a>
                        @if($canDelete)
                            <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this expense?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted text-center py-4">{{ $allFiltered ? 'No expenses match these filters.' : 'No expenses recorded yet.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($all->hasPages())
        <div class="card-body pt-3">{{ $all->fragment('all-expenses')->links() }}</div>
    @endif
</div>
@endif

@if($canSeeSummary)
{{-- Yearly summary by category (Admin / Finance) --}}
<details class="card border-0 shadow-sm mb-3" @if(request()->has('year')) open @endif>
    <summary class="card-header bg-transparent fw-semibold" style="cursor:pointer">Yearly summary by category — {{ $year }}</summary>
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 align-items-center mb-3">
            <input type="hidden" name="date" value="{{ $day->toDateString() }}">
            <select name="year" class="form-select form-select-sm" style="width:auto">
                @for($y = now()->year; $y >= now()->year - 4; $y--)
                    <option value="{{ $y }}" {{ (int) $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="exclude_billable" value="1" id="exclude_billable" {{ $excludeBillable ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="form-check-label" for="exclude_billable">Exclude billable</label>
            </div>
            <button class="btn btn-sm btn-outline-secondary">Apply</button>
        </form>
        <div class="table-responsive mb-3">
            <table class="table table-sm mb-0">
                <thead><tr><th>Category</th>@foreach(range(1, 12) as $mo)<th>{{ \Carbon\Carbon::create()->month($mo)->format('M') }}</th>@endforeach<th>Year</th></tr></thead>
                <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>{{ $category }}</td>
                        @foreach(range(1, 12) as $mo)<td>{{ $m($grid[$category][$mo] ?? 0) }}</td>@endforeach
                        <td class="fw-semibold">{{ $m(array_sum($grid[$category] ?? [])) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="text-muted">No expenses for {{ $year }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="fw-semibold small mb-1">Not billable by category</div>
                @forelse($nonBillableByCategory as $name => $total)
                    <div class="d-flex justify-content-between small"><span>{{ $name }}</span><span>{{ $m($total) }}</span></div>
                @empty
                    <div class="text-muted small">None</div>
                @endforelse
            </div>
            <div class="col-md-6">
                <div class="fw-semibold small mb-1">Billable by category</div>
                @forelse($billableByCategory as $name => $total)
                    <div class="d-flex justify-content-between small"><span>{{ $name }}</span><span>{{ $m($total) }}</span></div>
                @empty
                    <div class="text-muted small">None</div>
                @endforelse
            </div>
        </div>
    </div>
</details>
@endif
@endsection
