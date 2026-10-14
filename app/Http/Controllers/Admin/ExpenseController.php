<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Expense;
use App\Support\AuthScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->get('year', now()->year);
        $excludeBillable = $request->boolean('exclude_billable');

        $list = $this->baseQuery()->with(['client', 'brand'])->latest('expense_date')->paginate(25)->withQueryString();

        $reportQuery = $this->baseQuery()->whereYear('expense_date', $year);
        if ($excludeBillable) {
            $reportQuery->where('is_billable', false);
        }

        $rows = (clone $reportQuery)->get(['category', 'amount', 'tax_amount', 'expense_date', 'is_billable']);
        $categories = $rows->pluck('category')->unique()->sort()->values();
        $grid = [];
        foreach ($categories as $category) {
            $grid[$category] = array_fill(1, 12, 0.0);
        }
        $tax = array_fill(1, 12, 0.0);
        foreach ($rows as $row) {
            $month = (int) $row->expense_date->month;
            $grid[$row->category][$month] = ($grid[$row->category][$month] ?? 0) + (float) $row->amount;
            $tax[$month] += (float) $row->tax_amount;
        }

        $billableByCategory = $this->baseQuery()
            ->whereYear('expense_date', $year)
            ->where('is_billable', true)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $nonBillableByCategory = $this->baseQuery()
            ->whereYear('expense_date', $year)
            ->where('is_billable', false)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return view('admin.expenses.index', compact(
            'list', 'year', 'excludeBillable', 'grid', 'tax', 'categories',
            'billableByCategory', 'nonBillableByCategory'
        ));
    }

    public function create(): View
    {
        return view('admin.expenses.create', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'brands' => AuthScope::brandsForDropdown(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Expense::query()->create($this->payload($request));

        return redirect()->route('admin.expenses.index')->with('success', 'Expense added.');
    }

    public function edit(Expense $expense): View
    {
        return view('admin.expenses.edit', [
            'expense' => $expense,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'brands' => AuthScope::brandsForDropdown(),
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $this->payload($request);
        unset($data['created_by']);
        $expense->update($data);

        return redirect()->route('admin.expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()->route('admin.expenses.index')->with('success', 'Expense deleted.');
    }

    private function payload(Request $request): array
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'expense_date' => 'required|date',
            'note' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
            'brand_id' => AuthScope::brandIdRule(),
        ]);

        $validated['tax_amount'] = $validated['tax_amount'] ?? 0;
        $validated['client_id'] = $validated['client_id'] ?: null;
        $validated['brand_id'] = $validated['brand_id'] ?: null;
        $validated['is_billable'] = $request->boolean('is_billable');
        $validated['team_id'] = Auth::user()->team_id;
        $validated['created_by'] = $validated['created_by'] ?? Auth::id();

        return $validated;
    }

    private function baseQuery()
    {
        return Expense::query();
    }
}
