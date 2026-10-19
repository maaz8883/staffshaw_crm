<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Expense;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use App\Support\AuthScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    /**
     * Daily expenses: one day at a time (default today) with a quick-add form, that day's entries
     * and per-day totals for the month. The yearly category summary is for Admin / Finance.
     */
    public function index(Request $request): View
    {
        try {
            $day = $request->filled('date') ? \Illuminate\Support\Carbon::parse($request->get('date'))->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            $day = now()->startOfDay();
        }
        $monthStart = $day->copy()->startOfMonth();
        $monthEnd = $day->copy()->endOfMonth();

        $dayExpenses = $this->baseQuery()->with(['client', 'brand', 'creator'])
            ->whereDate('expense_date', $day->toDateString())
            ->latest('id')->get();
        $monthDays = $this->baseQuery()
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('DATE(expense_date) as d, SUM(amount) as total, COUNT(*) as entries')
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');

        $daily = [
            'day' => $day,
            'dayTotal' => (float) $dayExpenses->sum('amount'),
            'monthTotal' => (float) $monthDays->sum('total'),
            'monthDays' => $monthDays,
            'dayExpenses' => $dayExpenses,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'brands' => AuthScope::brandsForDropdown(),
            'canSeeSummary' => Auth::user()->hasRole([\App\Models\Role::ADMIN, \App\Models\Role::FINANCE]),
            'canDelete' => Auth::user()->hasRole(\App\Models\Role::ADMIN),
        ];

        // All expenses (Admin / Finance): every entry, newest first, with simple filters
        $all = null;
        $allTotal = 0.0;
        if ($daily['canSeeSummary']) {
            $allQuery = $this->baseQuery()
                ->when($request->filled('from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->get('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->get('to')))
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->get('category')))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                    ->where('note', 'like', '%'.$request->get('q').'%')
                    ->orWhere('category', 'like', '%'.$request->get('q').'%')));
            $allTotal = (float) (clone $allQuery)->sum('amount');
            $all = $allQuery->with(['client', 'brand', 'creator'])
                ->orderByDesc('expense_date')->orderByDesc('id')
                ->paginate(25, ['*'], 'page')->withQueryString();
        }
        $daily['all'] = $all;
        $daily['allTotal'] = $allTotal;
        $daily['allCategories'] = $daily['canSeeSummary'] ? $this->baseQuery()->distinct()->orderBy('category')->pluck('category') : collect();
        $daily['allFiltered'] = $request->hasAny(['from', 'to', 'category', 'q']) && collect($request->only(['from', 'to', 'category', 'q']))->filter()->isNotEmpty();

        $year = (int) $request->get('year', $day->year);
        $excludeBillable = $request->boolean('exclude_billable');

        $reportQuery = $this->baseQuery()->whereYear('expense_date', $year);
        if ($excludeBillable) {
            $reportQuery->where('is_billable', false);
        }

        $rows = (clone $reportQuery)->get(['category', 'amount', 'expense_date', 'is_billable']);
        $categories = $rows->pluck('category')->unique()->sort()->values();
        $grid = [];
        foreach ($categories as $category) {
            $grid[$category] = array_fill(1, 12, 0.0);
        }
        foreach ($rows as $row) {
            $month = (int) $row->expense_date->month;
            $grid[$row->category][$month] = ($grid[$row->category][$month] ?? 0) + (float) $row->amount;
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
            'year', 'excludeBillable', 'grid', 'categories',
            'billableByCategory', 'nonBillableByCategory'
        ) + $daily);
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
        $expense = Expense::query()->create($this->payload($request) + ['tax_amount' => 0]); // expenses carry no tax
        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_EXPENSE_CREATED,
            'Added expense: '.$this->describe($expense), ['expense_id' => $expense->id, 'amount' => (float) $expense->amount]);

        // Back to that day so the next entry can follow right away
        return redirect()->route('admin.expenses.index', ['date' => $expense->expense_date->toDateString()])->with('success', 'Expense added.');
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
        $before = $this->describe($expense);
        $expense->update($data);
        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_EXPENSE_UPDATED,
            'Updated expense #'.$expense->id.': '.$before.' → '.$this->describe($expense->fresh()), ['expense_id' => $expense->id, 'amount' => (float) $expense->amount]);

        return redirect()->route('admin.expenses.index', ['date' => $expense->fresh()->expense_date->toDateString()])->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $day = optional($expense->expense_date)->toDateString();
        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_EXPENSE_DELETED,
            'Deleted expense #'.$expense->id.': '.$this->describe($expense), ['expense_id' => $expense->id, 'amount' => (float) $expense->amount]);
        $expense->delete();

        return redirect()->route('admin.expenses.index', ['date' => $day])->with('success', 'Expense deleted.');
    }

    private function payload(Request $request): array
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'note' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
            'brand_id' => AuthScope::brandIdRule(),
        ]);

        $validated['client_id'] = ($validated['client_id'] ?? null) ?: null;
        $validated['brand_id'] = ($validated['brand_id'] ?? null) ?: null;
        $validated['is_billable'] = $request->boolean('is_billable');
        $validated['team_id'] = Auth::user()->team_id;
        $validated['created_by'] = $validated['created_by'] ?? Auth::id();

        return $validated;
    }

    /** "Software Rs 2,500 on 20 Oct 2026 (note)" — enough to see what changed in the activity log. */
    private function describe(Expense $expense): string
    {
        return trim($expense->category.' '.Expense::money($expense->amount)
            .' on '.optional($expense->expense_date)->format('d M Y')
            .($expense->note ? ' ('.\Illuminate\Support\Str::limit($expense->note, 60).')' : ''));
    }

    private function baseQuery()
    {
        return Expense::query();
    }
}
