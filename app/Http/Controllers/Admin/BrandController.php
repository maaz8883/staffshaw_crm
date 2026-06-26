<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
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
            'name'            => 'required|string|max:255',
            'website'         => 'required|url|max:255',
            'image'           => 'required|image|max:2048',
            'brief_document'  => ['nullable', 'file', 'max:10240', 'extensions:pdf,doc,docx'],
        ]);

        $validated['image'] = $request->file('image')->store('brands', 'public');

        if ($request->hasFile('brief_document')) {
            $validated = array_merge($validated, $this->storeBriefDocument($request->file('brief_document')));
        } else {
            unset($validated['brief_document']);
        }

        Brand::query()->create($validated);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand created successfully.');
    }

    public function show(Brand $brand): View
    {
        return view('admin.brands.show', compact('brand'));
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'website'         => 'required|url|max:255',
            'image'           => [
                'nullable',
                'image',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $brand): void {
                    if (! $request->hasFile('image') && ! $brand->image) {
                        $fail('The image field is required.');
                    }
                },
            ],
            'brief_document'  => ['nullable', 'file', 'max:10240', 'extensions:pdf,doc,docx'],
        ]);

        if ($request->hasFile('image')) {
            if ($brand->image) {
                Storage::disk('public')->delete($brand->image);
            }
            $validated['image'] = $request->file('image')->store('brands', 'public');
        } else {
            unset($validated['image']);
        }

        if ($request->hasFile('brief_document')) {
            if ($brand->brief_document) {
                Storage::disk('public')->delete($brand->brief_document);
            }
            $validated = array_merge($validated, $this->storeBriefDocument($request->file('brief_document')));
        } else {
            unset($validated['brief_document']);
        }

        $brand->update($validated);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->image) {
            Storage::disk('public')->delete($brand->image);
        }

        if ($brand->brief_document) {
            Storage::disk('public')->delete($brand->brief_document);
        }

        $brand->delete();

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand deleted successfully.');
    }

    /** @return array{brief_document: string, brief_document_name: string} */
    private function storeBriefDocument(UploadedFile $file): array
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
            'brief_document'      => $file->storeAs('brands/documents', Str::uuid() . '.' . $extension, 'public'),
            'brief_document_name' => $file->getClientOriginalName(),
        ];
    }
}
