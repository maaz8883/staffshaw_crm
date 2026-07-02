<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = App\Models\BrandBriefForm::query()->find(7);

if ($source === null || ! $source->hasSchema()) {
    fwrite(STDERR, "Source form #7 missing or has no schema.\n");
    exit(1);
}

$form = App\Models\BrandBriefForm::query()
    ->where('brand_id', 1)
    ->where('form_path', '/brief-form')
    ->first();

if ($form === null) {
    fwrite(STDERR, "Target /brief-form row not found.\n");
    exit(1);
}

$form->update([
    'name'           => $form->name ?: 'Brief Form',
    'slug'           => 'brief',
    'schema'         => $source->schema,
    'schema_version' => $source->schema_version ?? 1,
    'is_active'      => true,
]);

echo 'updated id=' . $form->id . ' fields=' . $form->fresh()->fieldCount() . ' title=' . ($form->fresh()->schema['title'] ?? '') . PHP_EOL;
