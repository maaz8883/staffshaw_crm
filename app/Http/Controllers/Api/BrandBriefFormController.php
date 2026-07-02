<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrandBriefForm;
use App\Services\BriefFormSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandBriefFormController extends Controller
{
    public function __construct(
        private readonly BriefFormSchemaService $schemaService
    ) {}

    public function resolve(Request $request): JsonResponse
    {
        $website = (string) $request->query('website', '');
        $path = (string) $request->query('path', '');
        $formId = (int) $request->query('form_id', 0);

        if ($website === '') {
            return response()->json(['error' => 'website is required.'], 422);
        }

        $form = $formId > 0
            ? $this->schemaService->resolveByIdForWebsite($formId, $website)
            : null;

        if ($form === null && $path !== '') {
            $form = $this->schemaService->resolveByWebsiteAndPath($website, $path);
        }

        if ($form === null) {
            return response()->json(['error' => 'Brief form not found.'], 404);
        }

        $form = $this->schemaService->ensureSchema($form);

        if (! $form->hasSchema()) {
            return response()->json(['error' => 'Brief form schema is not configured.'], 404);
        }

        return response()->json([
            'form' => $this->schemaService->serializeForApi($form),
        ]);
    }

    public function schema(BrandBriefForm $form): JsonResponse
    {
        if (! $form->is_active) {
            return response()->json(['error' => 'Brief form is inactive.'], 404);
        }

        $form = $this->schemaService->ensureSchema($form);

        if (! $form->hasSchema()) {
            return response()->json(['error' => 'Brief form schema is not configured.'], 404);
        }

        return response()->json([
            'form' => $this->schemaService->serializeForApi($form),
        ]);
    }
}
