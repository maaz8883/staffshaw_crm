<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BriefFormType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class BriefFormTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.brief-form-types.index');
    }

    public function datatable(): JsonResponse
    {
        $query = BriefFormType::query()
            ->select('brief_form_types.*')
            ->withCount('brandBriefForms');

        return DataTables::eloquent($query)
            ->editColumn('default_form_path', fn (BriefFormType $type) => e($type->default_form_path))
            ->editColumn('sort_order', fn (BriefFormType $type) => (string) $type->sort_order)
            ->addColumn('is_active_badge', function (BriefFormType $type) {
                return $type->is_active
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>';
            })
            ->addColumn('actions', function (BriefFormType $type) {
                $editUrl   = route('admin.brief-form-types.edit', $type);
                $deleteUrl = route('admin.brief-form-types.destroy', $type);
                $csrf      = csrf_field();
                $method    = method_field('DELETE');

                return '
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>
                    <form action="'.$deleteUrl.'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this brief form type?">
                        '.$csrf.$method.'
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                ';
            })
            ->rawColumns(['is_active_badge', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        return view('admin.brief-form-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBriefFormType($request);

        BriefFormType::query()->create($validated);

        return redirect()
            ->route('admin.brief-form-types.index')
            ->with('success', 'Brief form type created successfully.');
    }

    public function edit(BriefFormType $briefFormType): View
    {
        return view('admin.brief-form-types.edit', [
            'briefFormType' => $briefFormType,
        ]);
    }

    public function update(Request $request, BriefFormType $briefFormType): RedirectResponse
    {
        $validated = $this->validateBriefFormType($request, $briefFormType->id);

        $briefFormType->update($validated);

        return redirect()
            ->route('admin.brief-form-types.index')
            ->with('success', 'Brief form type updated successfully.');
    }

    public function destroy(BriefFormType $briefFormType): RedirectResponse
    {
        if ($briefFormType->brandBriefForms()->exists()) {
            return redirect()
                ->route('admin.brief-form-types.index')
                ->withErrors(['delete' => 'Cannot delete — used by brand brief forms. Deactivate instead.']);
        }

        $briefFormType->delete();

        return redirect()
            ->route('admin.brief-form-types.index')
            ->with('success', 'Brief form type deleted successfully.');
    }

    /** @return array<string, mixed> */
    private function validateBriefFormType(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = Rule::unique('brief_form_types', 'slug');

        if ($ignoreId !== null) {
            $slugRule = $slugRule->ignore($ignoreId);
        }

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'slug'              => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', $slugRule],
            'default_form_path' => ['required', 'string', 'max:255', 'regex:/^\//'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
            'is_active'         => ['nullable', 'boolean'],
        ]);

        $slug = strtolower(trim((string) ($validated['slug'] ?? '')));

        if ($slug === '') {
            $slug = Str::slug((string) $validated['name'], '-');
        }

        $validated['slug'] = $slug;
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
