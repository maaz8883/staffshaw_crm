<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandBriefForm;
use App\Support\BriefFormSchemaTemplates;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BriefFormSchemaService
{
    /** @var list<string> */
    public const FIELD_TYPES = [
        'text', 'email', 'tel', 'textarea', 'number',
        'radio', 'checkbox', 'select', 'file', 'section',
    ];

    public function defaultSchemaForType(string $slug): ?array
    {
        return BriefFormSchemaTemplates::defaultSchemaForSlug($slug);
    }

    /** @return array<string, string> */
    public function labelsFromSchema(?array $schema): array
    {
        if (! is_array($schema)) {
            return [];
        }

        $labels = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (! is_array($field) || ($field['type'] ?? '') === 'section') {
                    continue;
                }

                $id = (string) ($field['id'] ?? '');

                if ($id !== '') {
                    $labels[$id] = (string) ($field['label'] ?? $id);
                }
            }
        }

        return $labels;
    }

    /** @return list<string> */
    public function fieldIdsFromSchema(?array $schema): array
    {
        return array_keys($this->labelsFromSchema($schema));
    }

    /** @return array<string, mixed> */
    public function validateSchema(array $schema): array
    {
        if (! isset($schema['sections']) || ! is_array($schema['sections'])) {
            throw ValidationException::withMessages(['schema' => 'Schema must contain a sections array.']);
        }

        $ids = [];

        foreach ($schema['sections'] as $sectionIndex => $section) {
            if (! is_array($section)) {
                throw ValidationException::withMessages(['schema' => "Section #{$sectionIndex} is invalid."]);
            }

            foreach ($section['fields'] ?? [] as $fieldIndex => $field) {
                if (! is_array($field)) {
                    throw ValidationException::withMessages(['schema' => "Field #{$fieldIndex} in section #{$sectionIndex} is invalid."]);
                }

                $type = (string) ($field['type'] ?? '');

                if (! in_array($type, self::FIELD_TYPES, true)) {
                    throw ValidationException::withMessages(['schema' => "Unsupported field type: {$type}"]);
                }

                if ($type === 'section') {
                    continue;
                }

                $id = (string) ($field['id'] ?? '');

                if ($id === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
                    throw ValidationException::withMessages(['schema' => 'Each field needs a valid id (lowercase, underscores).']);
                }

                if (isset($ids[$id])) {
                    throw ValidationException::withMessages(['schema' => "Duplicate field id: {$id}"]);
                }

                $ids[$id] = true;

                if (($type === 'radio' || $type === 'select') && empty($field['options'])) {
                    throw ValidationException::withMessages(['schema' => "Field {$id} requires options."]);
                }
            }
        }

        $schema['version'] = (int) ($schema['version'] ?? 1);

        if (isset($schema['template'])) {
            $template = (string) $schema['template'];

            if (! in_array($template, ['custom', 'website', 'logo', 'ebook', 'book_cover'], true)) {
                throw ValidationException::withMessages(['schema' => 'Invalid template key.']);
            }
        }

        if (empty($schema['title'])) {
            $schema['title'] = 'Brief Form';
        }

        return $schema;
    }

    public function resolveByWebsiteAndPath(string $website, string $formPath): ?BrandBriefForm
    {
        $website = rtrim(trim($website), '/');
        $path = '/' . ltrim(trim($formPath), '/');

        return BrandBriefForm::query()
            ->where('is_active', true)
            ->where('form_path', $path)
            ->whereHas('brand', fn ($query) => $this->applyWebsiteMatch($query, $website))
            ->with(['brand', 'briefFormType'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    public function resolveByIdForWebsite(int $formId, string $website): ?BrandBriefForm
    {
        if ($formId <= 0) {
            return null;
        }

        $form = BrandBriefForm::query()
            ->where('id', $formId)
            ->where('is_active', true)
            ->with(['brand', 'briefFormType'])
            ->first();

        if ($form === null || $form->brand === null) {
            return null;
        }

        if (! $this->brandMatchesWebsite($form->brand, $website)) {
            return null;
        }

        return $form;
    }

    private function brandMatchesWebsite(Brand $brand, string $website): bool
    {
        $website = rtrim(trim($website), '/');
        $brandWebsite = rtrim(trim((string) $brand->website), '/');

        if ($brandWebsite === $website) {
            return true;
        }

        return str_starts_with($brandWebsite, $website . '/')
            || str_starts_with($website, $brandWebsite . '/');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Brand> $query */
    private function applyWebsiteMatch($query, string $website): void
    {
        $query->where(function ($q) use ($website) {
            $q->where('website', $website)
                ->orWhere('website', $website . '/')
                ->orWhere('website', 'like', $website . '/%');
        });
    }

    public function resolveSlugForForm(BrandBriefForm $form): string
    {
        if ($form->slug) {
            return $form->slug;
        }

        if ($form->briefFormType?->slug) {
            return $form->briefFormType->slug;
        }

        $path = trim((string) $form->form_path, '/');

        return preg_replace('/-brief$/', '', $path) ?: Str::slug($form->name);
    }

    /** @return array<string, mixed> */
    public function serializeForApi(BrandBriefForm $form): array
    {
        $form->loadMissing(['brand', 'briefFormType']);

        return [
            'id'        => $form->id,
            'slug'      => $this->resolveSlugForForm($form),
            'name'      => $form->name,
            'form_path' => $form->form_path,
            'schema'    => $form->schema,
            'brand'     => [
                'id'      => $form->brand_id,
                'name'    => $form->brand?->name,
                'website' => $form->brand?->website,
                'image'   => $form->brand?->image,
            ],
        ];
    }

    public function ensureSchema(BrandBriefForm $form): BrandBriefForm
    {
        if ($form->schema) {
            return $form;
        }

        $slug = $this->resolveSlugForForm($form);
        $schema = $this->defaultSchemaForType($slug);

        if ($schema) {
            $form->update([
                'slug'   => $form->slug ?: $slug,
                'schema' => $schema,
            ]);
        }

        return $form->fresh();
    }
}
