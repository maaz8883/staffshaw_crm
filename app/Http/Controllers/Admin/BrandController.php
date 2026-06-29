<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandBriefForm;
use App\Models\BriefFormType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        return DataTables::eloquent(Brand::query()->select('brands.*'))
            ->addColumn('image_thumb', function (Brand $brand) {
                if ($brand->image) {
                    return '<img src="' . asset('storage/' . $brand->image) . '" alt="' . e($brand->name) . '" style="height:36px;width:36px;object-fit:cover;border-radius:4px;">';
                }

                return '<span class="text-muted">—</span>';
            })
            ->editColumn('website', fn (Brand $brand) =>
                '<a href="' . e($brand->website) . '" target="_blank" rel="noopener">' . e($brand->website) . '</a>')
            ->addColumn('actions', function (Brand $brand) {
                $showUrl   = route('admin.brands.show', $brand);
                $editUrl   = route('admin.brands.edit', $brand);
                $deleteUrl = route('admin.brands.destroy', $brand);
                $csrf      = csrf_field();
                $method    = method_field('DELETE');

                return '
                    <a href="' . $showUrl . '" class="btn btn-sm btn-outline-info">View</a>
                    <a href="' . $editUrl . '" class="btn btn-sm btn-outline-warning">Edit</a>
                    <form action="' . $deleteUrl . '" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this brand?">
                        ' . $csrf . $method . '
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                ';
            })
            ->rawColumns(['image_thumb', 'website', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        return view('admin.brands.create', [
            'briefFormTypes' => $this->briefFormTypesForForm(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'website' => 'required|url|max:255',
            'image'   => 'required|image|max:2048',
            ...$this->briefFormsValidationRules(),
        ]);

        $validated['image'] = $request->file('image')->store('brands', 'public');
        unset($validated['brief_forms']);

        $brand = Brand::query()->create($validated);
        $this->syncBriefForms($brand, $request);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand created successfully.');
    }

    public function show(Brand $brand): View
    {
        $brand->load(['briefForms.briefFormType']);

        return view('admin.brands.show', compact('brand'));
    }

    public function edit(Brand $brand): View
    {
        $brand->load(['briefForms.briefFormType']);

        return view('admin.brands.edit', [
            'brand'          => $brand,
            'briefFormTypes' => $this->briefFormTypesForForm(),
        ]);
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'website' => 'required|url|max:255',
            'image'   => [
                'nullable',
                'image',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $brand): void {
                    if (! $request->hasFile('image') && ! $brand->image) {
                        $fail('The image field is required.');
                    }
                },
            ],
            ...$this->briefFormsValidationRules(),
        ]);

        if ($request->hasFile('image')) {
            if ($brand->image) {
                Storage::disk('public')->delete($brand->image);
            }
            $validated['image'] = $request->file('image')->store('brands', 'public');
        } else {
            unset($validated['image']);
        }

        unset($validated['brief_forms']);
        $brand->update($validated);
        $this->syncBriefForms($brand, $request);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->image) {
            Storage::disk('public')->delete($brand->image);
        }

        $brand->load('briefForms');

        foreach ($brand->briefForms as $briefForm) {
            $this->deleteBriefFormDocument($briefForm);
        }

        $brand->delete();

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand deleted successfully.');
    }

    /** @return array<string, mixed> */
    private function briefFormsValidationRules(): array
    {
        return [
            'brief_forms'                         => ['nullable', 'array'],
            'brief_forms.*.id'                    => ['nullable', 'integer', 'exists:brand_brief_forms,id'],
            'brief_forms.*.brief_form_type_id'    => ['nullable', 'integer', 'exists:brief_form_types,id'],
            'brief_forms.*.name'                  => ['required', 'string', 'max:255'],
            'brief_forms.*.form_path'               => ['required', 'string', 'max:255'],
            'brief_forms.*.document'              => ['nullable', 'file', 'max:10240', 'extensions:pdf,doc,docx'],
            'brief_forms.*.is_active'             => ['nullable', 'boolean'],
            'brief_forms.*._delete'               => ['nullable', 'boolean'],
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, BriefFormType> */
    private function briefFormTypesForForm()
    {
        return BriefFormType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'default_form_path']);
    }

    private function syncBriefForms(Brand $brand, Request $request): void
    {
        $rows = $request->input('brief_forms', []);

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                if (! empty($row['id'])) {
                    $existing = BrandBriefForm::query()
                        ->where('brand_id', $brand->id)
                        ->find($row['id']);

                    if ($existing) {
                        $this->deleteBriefFormDocument($existing);
                        $existing->delete();
                    }
                }

                continue;
            }

            $attributes = [
                'brief_form_type_id' => $row['brief_form_type_id'] ?? null,
                'name'               => $row['name'],
                'form_path'          => $this->normalizeFormPath($row['form_path']),
                'sort_order'         => (int) $index,
                'is_active'          => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];

            if (! empty($row['id'])) {
                $briefForm = BrandBriefForm::query()
                    ->where('brand_id', $brand->id)
                    ->findOrFail($row['id']);
                $briefForm->update($attributes);
            } else {
                $briefForm = $brand->briefForms()->create($attributes);
            }

            $file = $request->file("brief_forms.{$index}.document");

            if ($file instanceof UploadedFile) {
                $this->deleteBriefFormDocument($briefForm);
                $stored = $this->storeBriefFormDocument($file);
                $briefForm->update($stored);
            }
        }
    }

    private function normalizeFormPath(string $path): string
    {
        $path = trim($path);

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    /** @return array{document: string, document_name: string} */
    private function storeBriefFormDocument(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        if (! in_array($extension, ['pdf', 'doc', 'docx'], true)) {
            $extension = match ($file->getClientMimeType()) {
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/msword' => 'doc',
                'application/pdf' => 'pdf',
                default => 'pdf',
            };
        }

        return [
            'document'      => $file->storeAs('brands/documents', Str::uuid() . '.' . $extension, 'public'),
            'document_name' => $file->getClientOriginalName(),
        ];
    }

    private function deleteBriefFormDocument(BrandBriefForm $briefForm): void
    {
        if ($briefForm->document) {
            Storage::disk('public')->delete($briefForm->document);
        }
    }
}
