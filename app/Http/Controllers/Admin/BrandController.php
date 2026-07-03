<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandBriefForm;
use App\Services\BriefFormSchemaService;
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
        return view('admin.brands.create');
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
            ->route('admin.brands.edit', $brand)
            ->with('success', 'Brand created successfully.');
    }

    public function show(Brand $brand): View
    {
        $brand->load(['briefForms']);

        return view('admin.brands.show', compact('brand'));
    }

    public function edit(Brand $brand): View
    {
        $brand->load(['briefForms']);

        return view('admin.brands.edit', [
            'brand' => $brand,
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
            ->route('admin.brands.edit', $brand)
            ->with('success', 'Brand updated successfully.');
    }

    public function storeBriefForm(Request $request, Brand $brand): JsonResponse
    {
        if (! config('brief.builder_enabled')) {
            abort(404);
        }

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $briefForm = $this->createBriefFormRow($brand, [
            'name'      => $validated['name'],
            'is_active' => filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);

        return response()->json([
            'id'        => $briefForm->id,
            'name'      => $briefForm->name,
            'build_url' => route('admin.brands.brief-forms.builder', [$brand, $briefForm]),
            'is_active' => $briefForm->is_active,
        ]);
    }

    public function destroyBriefForm(Brand $brand, BrandBriefForm $form): JsonResponse
    {
        if ((int) $form->brand_id !== (int) $brand->id) {
            abort(404);
        }

        $this->deleteBriefFormRow($form);

        return response()->json(['success' => true]);
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
            'brief_forms.*.name'                  => ['required', 'string', 'max:255'],
            'brief_forms.*.form_path'             => ['nullable', 'string', 'max:255'],
            'brief_forms.*.document'              => ['nullable', 'file', 'max:10240', 'extensions:pdf,doc,docx'],
            'brief_forms.*.is_active'             => ['nullable', 'boolean'],
            'brief_forms.*._delete'               => ['nullable', 'boolean'],
        ];
    }

    private function syncBriefForms(Brand $brand, Request $request): void
    {
        $rows = $request->input('brief_forms', []);

        if (! is_array($rows)) {
            return;
        }

        $sortOrder = 0;

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
                        $this->deleteBriefFormRow($existing);
                    }
                }

                continue;
            }

            $attributes = [
                'name'       => $row['name'],
                'form_path'  => '/brief-form',
                'sort_order' => $sortOrder,
                'is_active'  => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];

            $sortOrder++;

            if (! empty($row['id'])) {
                $briefForm = BrandBriefForm::query()
                    ->where('brand_id', $brand->id)
                    ->findOrFail($row['id']);
                $briefForm->update($attributes);
            } else {
                $briefForm = $this->createBriefFormRow($brand, [
                    'name'       => $attributes['name'],
                    'sort_order' => $attributes['sort_order'],
                    'is_active'  => $attributes['is_active'],
                ]);
            }

            $file = $request->file("brief_forms.{$index}.document");

            if ($file instanceof UploadedFile) {
                $this->deleteBriefFormDocument($briefForm);
                $stored = $this->storeBriefFormDocument($file);
                $briefForm->update($stored);
            }
        }
    }

    /** @param array{name?: string, sort_order?: int, is_active?: bool} $row */
    private function createBriefFormRow(Brand $brand, array $row): BrandBriefForm
    {
        $name = (string) ($row['name'] ?? 'Brief Form');
        $sortOrder = isset($row['sort_order'])
            ? (int) $row['sort_order']
            : ((int) ($brand->briefForms()->max('sort_order') ?? -1) + 1);

        $briefForm = $brand->briefForms()->create([
            'name'       => $name,
            'form_path'  => '/brief-form',
            'sort_order' => $sortOrder,
            'is_active'  => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'slug'       => $this->uniqueSlugForBrand($brand, $name),
        ]);

        $this->applyDefaultBriefFormSchema($briefForm);

        return $briefForm;
    }

    private function uniqueSlugForBrand(Brand $brand, string $name, ?int $exceptFormId = null): string
    {
        $base = Str::slug($name) ?: 'brief-form';
        $slug = $base;
        $counter = 2;

        while (BrandBriefForm::query()
            ->where('brand_id', $brand->id)
            ->when($exceptFormId !== null, fn ($query) => $query->where('id', '!=', $exceptFormId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function applyDefaultBriefFormSchema(BrandBriefForm $form): void
    {
        if ($form->schema) {
            return;
        }

        $schema = app(BriefFormSchemaService::class)->defaultSchemaForType('website');

        if ($schema === null) {
            return;
        }

        $form->update([
            'schema'         => $schema,
            'schema_version' => (int) ($schema['version'] ?? 1),
        ]);
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

    private function deleteBriefFormRow(BrandBriefForm $briefForm): void
    {
        $this->deleteBriefFormDocument($briefForm);
        $briefForm->delete();
    }

    private function deleteBriefFormDocument(BrandBriefForm $briefForm): void
    {
        if ($briefForm->document) {
            Storage::disk('public')->delete($briefForm->document);
        }
    }
}
