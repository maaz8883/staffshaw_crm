<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrandBriefForm;
use App\Models\BriefSubmission;
use App\Models\Sale;
use App\Services\BriefFormSchemaService;
use App\Support\BriefSubmissionLabels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BriefSubmissionController extends Controller
{
    public function __construct(
        private readonly BriefFormSchemaService $schemaService
    ) {}

    public function showForSale(Request $request, Sale $sale): JsonResponse
    {
        $brandBriefFormId = (int) $request->query('brand_brief_form_id', 0);
        $briefType = trim((string) $request->query('brief_type', ''));

        $submission = $this->findSubmittedForSale(
            $sale,
            $brandBriefFormId > 0 ? $brandBriefFormId : null,
            $briefType !== '' ? $briefType : null
        );

        if ($submission === null) {
            return response()->json(['error' => 'Brief submission not found.'], 404);
        }

        return response()->json([
            'sale_id'    => $sale->id,
            'submission' => $this->serializeSubmission($submission),
        ]);
    }

    public function show(Sale $sale, string $briefType): JsonResponse
    {
        if (! preg_match('/^[a-z0-9_-]+$/', $briefType)) {
            return response()->json(['error' => 'Invalid brief type.'], 422);
        }

        $submission = $this->findSubmittedForSale($sale, null, $briefType);

        if ($submission === null) {
            return response()->json(['error' => 'Brief submission not found.'], 404);
        }

        return response()->json([
            'sale_id'    => $sale->id,
            'submission' => $this->serializeSubmission($submission),
        ]);
    }

    public function store(Request $request, Sale $sale): JsonResponse
    {
        $validated = $request->validate([
            'brand_brief_form_id' => 'nullable|integer|exists:brand_brief_forms,id',
            'brief_type'          => 'required|string|max:50|regex:/^[a-z0-9_-]+$/',
            'form_path'           => 'required|string|max:100',
            'data'                => 'required|array',
            'attachments'         => 'nullable|array',
            'status'              => 'nullable|string|max:20|in:' . BriefSubmission::STATUS_PENDING . ',' . BriefSubmission::STATUS_SUBMITTED,
            'client_name'         => 'nullable|string|max:255',
            'client_email'        => 'nullable|email|max:255',
            'client_ip'           => 'nullable|string|max:45',
        ]);

        $brandBriefForm = null;

        if (! empty($validated['brand_brief_form_id'])) {
            $brandBriefForm = BrandBriefForm::query()->find($validated['brand_brief_form_id']);

            if ($brandBriefForm === null) {
                throw ValidationException::withMessages(['brand_brief_form_id' => 'Invalid brief form.']);
            }

            $validated['brief_type'] = $this->schemaService->resolveSlugForForm($brandBriefForm);
            $this->validateDataAgainstSchema($brandBriefForm, $validated['data']);
        }

        $now = now();
        $status = $validated['status'] ?? BriefSubmission::STATUS_SUBMITTED;

        $lookup = ['sale_id' => $sale->id];

        if (! empty($validated['brand_brief_form_id'])) {
            $lookup['brand_brief_form_id'] = $validated['brand_brief_form_id'];
        } else {
            $lookup['brief_type'] = $validated['brief_type'];
        }

        $existing = BriefSubmission::query()->where($lookup)->first();

        if ($existing !== null && $existing->isSubmitted()) {
            return response()->json(['error' => 'This brief has already been submitted.'], 409);
        }

        $meta = $this->buildSubmissionMeta($brandBriefForm);

        $submission = BriefSubmission::query()->updateOrCreate(
            $lookup,
            [
                'brand_brief_form_id' => $validated['brand_brief_form_id'] ?? null,
                'brief_type'          => $validated['brief_type'],
                'form_path'           => $validated['form_path'],
                'data'                => $validated['data'],
                'attachments'         => $validated['attachments'] ?? null,
                'meta'                => $meta,
                'status'              => $status,
                'submitted_at'        => $status === BriefSubmission::STATUS_SUBMITTED ? $now : null,
                'client_name'         => $validated['client_name'] ?? null,
                'client_email'        => $validated['client_email'] ?? null,
                'client_ip'           => $validated['client_ip'] ?? $request->ip(),
            ]
        );

        return response()->json([
            'sale_id'    => $sale->id,
            'submission' => $this->serializeSubmission($submission),
        ], $submission->wasRecentlyCreated ? 201 : 200);
    }

    public function upload(Request $request, Sale $sale): JsonResponse
    {
        $validated = $request->validate([
            'field' => 'required|string|max:100|regex:/^[a-z][a-z0-9_]*$/',
            'file'  => 'required|file|max:10240',
        ]);

        $path = $request->file('file')->store(
            'brief-uploads/' . $sale->id . '/' . $validated['field'],
            'public'
        );

        return response()->json([
            'field'         => $validated['field'],
            'original_name' => $request->file('file')->getClientOriginalName(),
            'public_url'    => Storage::disk('public')->url($path),
            'path'          => $path,
        ]);
    }

    private function findSubmittedForSale(Sale $sale, ?int $brandBriefFormId, ?string $briefType): ?BriefSubmission
    {
        if ($brandBriefFormId !== null) {
            $submission = BriefSubmission::query()
                ->where('sale_id', $sale->id)
                ->where('brand_brief_form_id', $brandBriefFormId)
                ->first();

            if ($submission !== null && $submission->isSubmitted()) {
                return $submission;
            }

            return null;
        }

        $typesToTry = array_values(array_unique(array_filter([
            $briefType,
            'brief',
            'website',
            'logo',
            'ebook',
        ])));

        foreach ($typesToTry as $type) {
            $submission = BriefSubmission::query()
                ->where('sale_id', $sale->id)
                ->where('brief_type', $type)
                ->first();

            if ($submission !== null && $submission->isSubmitted()) {
                return $submission;
            }
        }

        return BriefSubmission::query()
            ->where('sale_id', $sale->id)
            ->where('status', BriefSubmission::STATUS_SUBMITTED)
            ->where('form_path', '/brief-form')
            ->orderByDesc('submitted_at')
            ->first();
    }

    /** @return array<string, mixed>|null */
    private function buildSubmissionMeta(?BrandBriefForm $brandBriefForm): ?array
    {
        if ($brandBriefForm === null) {
            return null;
        }

        $brandBriefForm = $this->schemaService->ensureSchema($brandBriefForm);

        return [
            'schema_title'   => (string) ($brandBriefForm->schema['title'] ?? $brandBriefForm->name ?? 'Brief Form'),
            'schema_version' => (int) ($brandBriefForm->schema_version ?? 0),
            'field_labels'   => $brandBriefForm->fieldLabels(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateDataAgainstSchema(BrandBriefForm $form, array $data): void
    {
        $form = $this->schemaService->ensureSchema($form);

        if (! $form->hasSchema()) {
            return;
        }

        $allowedIds = $this->schemaService->fieldIdsFromSchema($form->schema);

        foreach (array_keys($data) as $key) {
            if (! in_array($key, $allowedIds, true)) {
                throw ValidationException::withMessages(['data' => "Unknown field: {$key}"]);
            }
        }

        foreach ($form->schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (! is_array($field) || ($field['type'] ?? '') === 'section' || ($field['type'] ?? '') === 'file') {
                    continue;
                }

                if (! empty($field['required'])) {
                    $id = (string) ($field['id'] ?? '');
                    $value = $data[$id] ?? null;

                    if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                        throw ValidationException::withMessages(['data' => "Field {$id} is required."]);

                    }
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function serializeSubmission(BriefSubmission $submission): array
    {
        $submission->loadMissing('brandBriefForm');

        $meta = is_array($submission->meta) ? $submission->meta : [];
        $fieldLabels = $meta['field_labels'] ?? null;

        if (! is_array($fieldLabels) || $fieldLabels === []) {
            $fieldLabels = $submission->brandBriefForm
                ? $submission->brandBriefForm->fieldLabels()
                : BriefSubmissionLabels::forType((string) $submission->brief_type);
        }

        $schemaTitle = $meta['schema_title'] ?? null;

        if ($schemaTitle === null || $schemaTitle === '') {
            $schemaTitle = match ((string) $submission->brief_type) {
                'website'    => 'Website Brief Form',
                'logo'       => 'Logo Brief',
                'ebook'      => 'Ebook Brief',
                'book_cover' => 'Book Cover Design Brief',
                default      => $submission->brandBriefForm?->name ?? 'Brief Form',
            };
        }

        return [
            'id'                  => $submission->id,
            'sale_id'             => $submission->sale_id,
            'brand_brief_form_id' => $submission->brand_brief_form_id,
            'brief_type'          => $submission->brief_type,
            'form_path'           => $submission->form_path,
            'status'              => $submission->status,
            'submitted_at'        => $submission->submitted_at?->toIso8601String(),
            'client_name'         => $submission->client_name,
            'client_email'        => $submission->client_email,
            'client_ip'           => $submission->client_ip,
            'data'                => $submission->data ?? [],
            'attachments'         => $submission->attachments ?? [],
            'schema_title'        => $schemaTitle,
            'field_labels'        => $fieldLabels,
            'meta'                => $meta,
        ];
    }
}
