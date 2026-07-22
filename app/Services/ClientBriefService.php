<?php

namespace App\Services;

use App\Models\BrandBriefForm;
use App\Models\BriefSubmission;
use App\Models\Client;
use App\Models\Sale;
use App\Support\BriefSubmissionDisplay;
use App\Support\BriefSubmissionLabels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ClientBriefService
{
    /** @return array<string, mixed> */
    public function forClient(Client $client): array
    {
        $sales = $client->sales()
            ->where('is_draft', false)
            ->with([
                'brand',
                'brand.briefForms' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('briefFormType'),
            ])
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->get();

        $submissionsBySale = BriefSubmission::query()
            ->whereIn('sale_id', $sales->pluck('id'))
            ->with('brandBriefForm.briefFormType')
            ->orderBy('id')
            ->get()
            ->groupBy('sale_id');

        $briefForms = collect();

        foreach ($sales as $sale) {
            $saleSubmissions = $submissionsBySale->get($sale->id, collect())->values();
            $usedSubmissionIds = [];
            $forms = $sale->brand?->briefForms ?? collect();

            foreach ($forms as $form) {
                $submission = $this->matchSubmission(
                    $form,
                    $saleSubmissions,
                    $usedSubmissionIds,
                    $forms->count() === 1
                );

                if ($submission !== null) {
                    $usedSubmissionIds[] = (int) $submission->id;
                }

                $briefForms->push($this->serializeBrief($client, $sale, $form, $submission));
            }

            foreach ($saleSubmissions as $submission) {
                if (in_array((int) $submission->id, $usedSubmissionIds, true)) {
                    continue;
                }

                $briefForms->push($this->serializeBrief(
                    $client,
                    $sale,
                    $submission->brandBriefForm,
                    $submission
                ));
            }
        }

        return [
            'client' => [
                'id'    => $client->id,
                'name'  => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
            ],
            'summary' => [
                'sales'     => $sales->count(),
                'total'     => $briefForms->count(),
                'submitted' => $briefForms->where('status', BriefSubmission::STATUS_SUBMITTED)->count(),
                'pending'   => $briefForms->where('status', BriefSubmission::STATUS_PENDING)->count(),
            ],
            'brief_forms' => $briefForms->values()->all(),
        ];
    }

    /**
     * @return list<array{index: int, field: string|null, name: string, size: int|null, mime_type: string|null}>
     */
    public function filesForSubmission(BriefSubmission $submission): array
    {
        $submission->loadMissing(['sale', 'brandBriefForm']);

        if ($submission->sale === null) {
            return [];
        }

        $entries = [];
        $this->collectFileEntries($submission->attachments, null, $entries);

        $schema = $submission->brandBriefForm?->schema;

        foreach ($this->fileFieldIds($schema) as $fieldId) {
            if (array_key_exists($fieldId, $submission->data ?? [])) {
                $this->collectFileEntries($submission->data[$fieldId], $fieldId, $entries);
            }
        }

        $files = [];
        $seenPaths = [];

        foreach ($entries as $entry) {
            $path = $this->normalizeUploadPath($entry['path'] ?? null, (int) $submission->sale_id);

            if ($path === null || isset($seenPaths[$path]) || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $seenPaths[$path] = true;
            $files[] = [
                'index'     => count($files),
                'field'     => $entry['field'] ?? null,
                'name'      => $this->safeFilename($entry['name'] ?? null, $path),
                'size'      => $this->fileSize($path),
                'mime_type' => $this->mimeType($path),
                '_path'     => $path,
            ];
        }

        return $files;
    }

    /** @return array{index: int, field: string|null, name: string, size: int|null, mime_type: string|null, _path: string}|null */
    public function fileForSubmission(BriefSubmission $submission, int $index): ?array
    {
        return collect($this->filesForSubmission($submission))
            ->first(fn (array $file) => $file['index'] === $index);
    }

    /**
     * @param Collection<int, BriefSubmission> $submissions
     * @param list<int> $usedSubmissionIds
     */
    private function matchSubmission(
        BrandBriefForm $form,
        Collection $submissions,
        array $usedSubmissionIds,
        bool $allowSingleFallback
    ): ?BriefSubmission {
        $available = $submissions->reject(
            fn (BriefSubmission $submission) => in_array((int) $submission->id, $usedSubmissionIds, true)
        );

        $byId = $available->first(
            fn (BriefSubmission $submission) => (int) $submission->brand_brief_form_id === (int) $form->id
        );

        if ($byId !== null) {
            return $byId;
        }

        $types = collect([
            $form->briefFormType?->slug,
            $form->resolveSlug(),
            preg_replace('/-brief$/', '', trim((string) $form->form_path, '/')),
        ])->filter()->unique()->values();

        $byType = $available->first(
            fn (BriefSubmission $submission) => $types->contains((string) $submission->brief_type)
        );

        if ($byType !== null) {
            return $byType;
        }

        return $allowSingleFallback && $available->count() === 1 ? $available->first() : null;
    }

    /** @return array<string, mixed> */
    private function serializeBrief(
        Client $client,
        Sale $sale,
        ?BrandBriefForm $form,
        ?BriefSubmission $submission
    ): array {
        $schema = $form?->schema ?? $submission?->brandBriefForm?->schema;
        $labels = $this->fieldLabels($form, $submission);
        $data = is_array($submission?->data) ? $submission->data : [];

        foreach ($this->fileFieldIds($schema) as $fileFieldId) {
            unset($data[$fileFieldId]);
        }

        $files = $submission ? $this->filesForSubmission($submission) : [];
        $publicFiles = array_map(function (array $file) use ($client, $submission) {
            unset($file['_path']);
            $file['download_url'] = route('api.clients.brief-files.download', [
                $client,
                $submission,
                $file['index'],
            ]);

            return $file;
        }, $files);

        $document = null;

        if ($form !== null && $form->hasDocument()) {
            $resolved = $form->resolveDocumentPath();
            $document = [
                'name'         => $resolved['filename'],
                'download_url' => route('api.clients.brief-documents.download', [$client, $form]),
            ];
        }

        $status = $submission?->status ?? BriefSubmission::STATUS_PENDING;
        $schemaTitle = $submission?->meta['schema_title'] ?? null;

        return [
            'key' => sprintf(
                'sale-%d-%s-%d',
                $sale->id,
                $form ? 'form' : 'submission',
                $form?->id ?? $submission?->id ?? 0
            ),
            'sale' => [
                'id'        => $sale->id,
                'title'     => $sale->title,
                'sale_date' => $sale->sale_date?->toDateString(),
                'sale_type' => $sale->sale_type,
                'status'    => $sale->status,
                'brand'     => $sale->brand ? [
                    'id'   => $sale->brand->id,
                    'name' => $sale->brand->name,
                ] : null,
            ],
            'form_id'       => $form?->id,
            'submission_id' => $submission?->id,
            'name'          => $form?->name
                ?? $schemaTitle
                ?? ucfirst(str_replace('_', ' ', (string) $submission?->brief_type)),
            'brief_type'    => $submission?->brief_type ?? $form?->resolveSlug(),
            'status'        => $status,
            'submitted_at'  => $submission?->submitted_at?->toIso8601String(),
            'client_name'   => $submission?->client_name,
            'client_email'  => $submission?->client_email,
            'answers'       => $submission
                ? BriefSubmissionDisplay::displayBlocks($schema, $data, $labels)
                : [],
            'files'         => $publicFiles,
            'document'      => $document,
        ];
    }

    /** @return array<string, string> */
    private function fieldLabels(?BrandBriefForm $form, ?BriefSubmission $submission): array
    {
        $snapshot = $submission?->meta['field_labels'] ?? null;

        if (is_array($snapshot) && $snapshot !== []) {
            return $snapshot;
        }

        if ($form?->hasSchema()) {
            return $form->fieldLabels();
        }

        if ($submission?->brandBriefForm?->hasSchema()) {
            return $submission->brandBriefForm->fieldLabels();
        }

        return BriefSubmissionLabels::forType((string) $submission?->brief_type);
    }

    /** @return list<string> */
    private function fileFieldIds(?array $schema): array
    {
        $ids = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (is_array($field) && ($field['type'] ?? null) === 'file' && ! empty($field['id'])) {
                    $ids[] = (string) $field['id'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param list<array{path?: string, name?: string, field?: string|null}> $entries */
    private function collectFileEntries(mixed $value, ?string $field, array &$entries): void
    {
        if (is_string($value)) {
            $entries[] = ['path' => $value, 'name' => basename($value), 'field' => $field];
            return;
        }

        if (! is_array($value)) {
            return;
        }

        $path = $value['path'] ?? $value['public_url'] ?? $value['url'] ?? null;

        if (is_string($path) && $path !== '') {
            $entries[] = [
                'path'  => $path,
                'name'  => $value['original_name'] ?? $value['filename'] ?? $value['name'] ?? null,
                'field' => $value['field'] ?? $field,
            ];
            return;
        }

        foreach ($value as $key => $item) {
            $nestedField = is_string($key) && ! ctype_digit($key) ? $key : $field;
            $this->collectFileEntries($item, $nestedField, $entries);
        }
    }

    private function normalizeUploadPath(?string $value, int $saleId): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $path = str_replace('\\', '/', $value);

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $storagePosition = strpos($path, '/storage/');

        if ($storagePosition !== false) {
            $path = substr($path, $storagePosition + strlen('/storage/'));
        }

        $path = ltrim(rawurldecode($path), '/');
        $expectedPrefix = 'brief-uploads/'.$saleId.'/';

        if (str_contains($path, "\0") || str_contains($path, '../') || ! str_starts_with($path, $expectedPrefix)) {
            return null;
        }

        return $path;
    }

    private function safeFilename(?string $name, string $path): string
    {
        $filename = basename(str_replace('\\', '/', (string) ($name ?: $path)));
        $filename = str_replace(["\r", "\n", '"'], '', $filename);

        return $filename !== '' ? $filename : 'brief-file';
    }

    private function fileSize(string $path): ?int
    {
        try {
            return Storage::disk('public')->size($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private function mimeType(string $path): ?string
    {
        try {
            return Storage::disk('public')->mimeType($path) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
