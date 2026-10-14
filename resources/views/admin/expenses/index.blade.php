@extends('admin.layout')
@section('title', 'Expenses')
@section('page-title', 'Expenses')
@section('page-icon', 'wallet2')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('admin.expenses.create') }}" class="btn btn-primary">New Expense</a>
    <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="year" class="form-select form-select-sm" style="width:auto">
            @for($y = now()->year; $y >= now()->year - 4; $y--)
                <option value="{{ $y }}" {{ (int) $year === $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" name="exclude_billable" value="1" id="exclude_billable" {{ $excludeBillable ? 'checked' : '' }} onchange="this.form.submit()">
            <label class="form-check-label" for="exclude_billable">Exclude Billable Expenses</label>
        </div>
        <button class="btn btn-sm btn-outline-secondary">Apply</button>
    </form>
</div>

<div class="card mb-3"><div class="card-body table-responsive">
    <table class="table table-sm mb-0">
        <thead>
        <tr>
            <th>Category</th>
            @foreach(range(1, 12) as $m)
                <th>{{ \Carbon\Carbon::create()->month($m)->format('M') }}</th>
            @endforeach
            <th>Year</th>
        </tr>
        </thead>
        <tbody>
        @forelse($categories as $category)
            @php $sum = array_sum($grid[$category] ?? []); @endphp
            <tr>
                <td>{{ $category }}</td>
                @foreach(range(1, 12) as $m)
                    <td>${{ number_format($grid[$category][$m] ?? 0, 2) }}</td>
                @endforeach
                <td>${{ number_format($sum, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="14" class="text-muted">No expenses for {{ $year }}.</td></tr>
        @endforelse
        @if($categories->isNotEmpty())
            <tr>
                <td>Total Tax</td>
                @foreach(range(1, 12) as $m)
                    <td>${{ number_format($tax[$m] ?? 0, 2) }}</td>
                @endforeach
                <td>${{ number_format(array_sum($tax), 2) }}</td>
            </tr>
        @endif
        </tbody>
    </table>
</div></div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card"><div class="card-header">Not billable by category</div><div class="card-body">
            @forelse($nonBillableByCategory as $name => $total)
                <div class="d-flex justify-content-between"><span>{{ $name }}</span><span>${{ number_format($total, 2) }}</span></div>
            @empty
                <div class="text-muted">None</div>
            @endforelse
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header">Billable by category</div><div class="card-body">
            @forelse($billableByCategory as $name => $total)
                <div class="d-flex justify-content-between"><span>{{ $name }}</span><span>${{ number_format($total, 2) }}</span></div>
            @empty
                <div class="text-muted">None</div>
            @endforelse
        </div></div>
    </div>
</div>

<div class="card"><div class="card-body table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Category</th><th>Customer</th><th>Amount</th><th>Tax</th><th>Billable</th><th></th></tr></thead>
        <tbody>
        @forelse($list as $expense)
            <tr>
                <td>{{ $expense->expense_date?->format('Y-m-d') }}</td>
                <td>{{ $expense->category }}</td>
                <td>{{ $expense->client?->name ?? '-' }}</td>
                <td>${{ number_format($expense->amount, 2) }}</td>
                <td>${{ number_format($expense->tax_amount, 2) }}</td>
                <td>{{ $expense->is_billable ? 'Yes' : 'No' }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.expenses.edit', $expense) }}" class="btn btn-sm btn-outline-warning">Edit</a>
                    <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this expense?">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-muted">No expenses yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="mt-3">{{ $list->links() }}</div>
</div></div>
@endsection
