<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandBriefForm;
use App\Services\BriefFormSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BriefFormBuilderController extends Controller
{
    public function __construct(
        private readonly BriefFormSchemaService $schemaService
    ) {}

    public function show(Brand $brand, BrandBriefForm $form): View
    {
        $this->ensureBuilderEnabled();
        $this->ensureFormBelongsToBrand($brand, $form);
        $form = $this->schemaService->ensureSchema($form);

        $templates = [
            'custom'     => $this->schemaService->defaultSchemaForType('custom'),
            'website'    => $this->schemaService->defaultSchemaForType('website'),
            'logo'       => $this->schemaService->defaultSchemaForType('logo'),
            'ebook'      => $this->schemaService->defaultSchemaForType('ebook'),
            'book_cover' => $this->schemaService->defaultSchemaForType('book_cover'),
        ];

        return view('admin.brands.brief-form-builder', compact('brand', 'form', 'templates'));
    }

    public function update(Request $request, Brand $brand, BrandBriefForm $form): RedirectResponse
    {
        $this->ensureBuilderEnabled();
        $this->ensureFormBelongsToBrand($brand, $form);

        $payload = $request->input('schema');

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (! is_array($payload)) {
            return back()->withErrors(['schema' => 'Invalid schema payload.']);
        }

        $validated = $this->schemaService->validateSchema($payload);

        $form->update([
            'schema'         => $validated,
            'schema_version' => (int) ($validated['version'] ?? 1),
            'slug'           => $form->slug ?: $this->schemaService->resolveSlugForForm($form),
        ]);

        return redirect()
            ->route('admin.brands.brief-forms.builder', [$brand, $form])
            ->with('success', 'Brief form schema saved.');
    }

    public function preview(Request $request, Brand $brand, BrandBriefForm $form): JsonResponse
    {
        $this->ensureBuilderEnabled();
        $this->ensureFormBelongsToBrand($brand, $form);

        $payload = $request->input('schema');

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (! is_array($payload)) {
            return response()->json(['error' => 'Invalid schema'], 422);
        }

        try {
            $validated = $this->schemaService->validateSchema($payload);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['schema' => $validated]);
    }

    private function ensureFormBelongsToBrand(Brand $brand, BrandBriefForm $form): void
    {
        if ((int) $form->brand_id !== (int) $brand->id) {
            abort(404);
        }
    }

    private function ensureBuilderEnabled(): void
    {
        if (! config('brief.builder_enabled', false)) {
            abort(404);
        }
    }
}
