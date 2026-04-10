<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\AuthScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CompanyController extends Controller
{
    public function index(): View
    {
        return view('admin.companies.index');
    }

    public function datatable(): JsonResponse
    {
        $query = Company::query()
            ->select('companies.*')
            ->withCount(['teams', 'users']);

        if (AuthScope::isAgent()) {
            $query->where('id', auth()->user()->company_id);
        }

        return DataTables::eloquent($query)
            ->editColumn('description', fn (Company $c) => e($c->description ?: '-'))
            ->addColumn('logo', function (Company $c) {
                if ($c->logo) {
                    return '<img src="'.asset('storage/'.$c->logo).'" alt="logo" style="height:36px;width:36px;object-fit:cover;border-radius:4px;">';
                }
                return '<span class="text-muted">—</span>';
            })
            ->addColumn('actions', function (Company $company) {
                $editUrl   = route('admin.companies.edit', $company);
                $deleteUrl = route('admin.companies.destroy', $company);
                $csrf      = csrf_field();
                $method    = method_field('DELETE');

                return '
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>
                    <form action="'.$deleteUrl.'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this company?">
                        '.$csrf.$method.'
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                ';
            })
            ->rawColumns(['logo', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        return view('admin.companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:companies,name',
            'logo'        => 'nullable|image|max:2048',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('companies', 'public');
        }

        Company::query()->create($validated);

        return redirect()->route('admin.companies.index')->with('success', 'Company created successfully.');
    }

    public function edit(Company $company): View
    {
        return view('admin.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:companies,name,' . $company->id,
            'logo'        => 'nullable|image|max:2048',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($company->logo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($company->logo);
            }
            $validated['logo'] = $request->file('logo')->store('companies', 'public');
        } else {
            unset($validated['logo']);
        }

        $company->update($validated);

        return redirect()->route('admin.companies.index')->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('admin.companies.index')->with('success', 'Company deleted successfully.');
    }

    // AJAX: return teams for a given company
    public function teams(Company $company): JsonResponse
    {
        return response()->json(
            $company->teams()->orderBy('name')->get(['id', 'name'])
        );
    }
}
