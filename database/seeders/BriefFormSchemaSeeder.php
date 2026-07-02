<?php

namespace Database\Seeders;

use App\Models\BrandBriefForm;
use App\Services\BriefFormSchemaService;
use Illuminate\Database\Seeder;

class BriefFormSchemaSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(BriefFormSchemaService::class);

        BrandBriefForm::query()->with('briefFormType')->each(function (BrandBriefForm $form) use ($service) {
            $slug = $service->resolveSlugForForm($form);
            $updates = [];

            if (! $form->slug) {
                $updates['slug'] = $slug;
            }

            if (! $form->schema) {
                $schema = $service->defaultSchemaForType('custom') ?? $service->defaultSchemaForType($slug);

                if ($schema) {
                    $updates['schema'] = $schema;
                    $updates['schema_version'] = (int) ($schema['version'] ?? 1);
                }
            }

            if ($updates !== []) {
                $form->update($updates);
            }
        });
    }
}
