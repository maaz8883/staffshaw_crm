<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\User;
use App\Support\AuthScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class BrandController extends Controller
{
    public function index(): View
    {
        return view('admin.brands.index');
    }

    public function datatable(): JsonResponse
    {
        $query = Brand::query()
            ->with('assignedUser')
            ->select('brands.*');

        AuthScope::scopeBrands($query);

        return DataTables::eloquent($query)
            ->editColumn('status', fn (Brand $brand) => '<span class="badge bg-' . ($brand->status === 'active' ? 'success' : 'secondary') . '">' . ucfirst($brand->status) . '</span>')
            ->addColumn('assigned_to', fn (Brand $brand) => e($brand->assignedUser?->name ?? '-'))
            ->addColumn('actions', function (Brand $brand) {
                $showUrl = route('admin.brands.show', $brand);
                $editUrl = route('admin.brands.edit', $brand);
                $deleteUrl = route('admin.brands.destroy', $brand);
                $csrf = csrf_field();
                $method = method_field('DELETE');
                $isAgent = auth()->user()?->hasRole('Agent');

                $html = '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a>
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>';
                if (!$isAgent) {
                    $html .= '
                    <form action="'.$deleteUrl.'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this brand?">
                        '.$csrf.$method.'
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>';
                }
                return $html;
            })
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $users = AuthScope::usersForDropdown();

        return view('admin.brands.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'industry'         => 'nullable|string|max:255',
            'website'          => 'nullable|url|max:255',
            'email'            => 'nullable|email|max:255',
            'phone'            => 'nullable|string|max:50',
            'address'          => 'nullable|string',
            'status'           => 'required|in:active,inactive',
            'notes'            => 'nullable|string',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        // Auto-assign company for agents
        if (AuthScope::isAgent()) {
            $validated['company_id'] = auth()->user()->company_id;
        }

        Brand::query()->create($validated);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand created successfully.');
    }

    public function show(Brand $brand): View
    {
        $brand->load('assignedUser');

        return view('admin.brands.show', compact('brand'));
    }

    public function edit(Brand $brand): View
    {
        $users = AuthScope::usersForDropdown();

        return view('admin.brands.edit', compact('brand', 'users'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'industry'         => 'nullable|string|max:255',
            'website'          => 'nullable|url|max:255',
            'email'            => 'nullable|email|max:255',
            'phone'            => 'nullable|string|max:50',
            'address'          => 'nullable|string',
            'status'           => 'required|in:active,inactive',
            'notes'            => 'nullable|string',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        $brand->update($validated);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand deleted successfully.');
    }
}
