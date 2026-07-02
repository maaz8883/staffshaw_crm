<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $brandIds = DB::table('brand_brief_forms')
            ->distinct()
            ->pluck('brand_id');

        foreach ($brandIds as $brandId) {
            $forms = DB::table('brand_brief_forms')
                ->where('brand_id', $brandId)
                ->get();

            if ($forms->isEmpty()) {
                continue;
            }

            $keeper = $forms
                ->sort(function ($a, $b) {
                    return $this->briefFormScore($b) <=> $this->briefFormScore($a);
                })
                ->first();
            $keeperName = $keeper->name ?: 'Brief Form';

            DB::table('brand_brief_forms')
                ->where('id', $keeper->id)
                ->update([
                    'name'       => $keeperName,
                    'form_path'  => '/brief-form',
                    'slug'       => 'brief',
                    'is_active'  => true,
                    'sort_order' => 0,
                    'updated_at' => now(),
                ]);

            $duplicateIds = $forms->pluck('id')->filter(fn ($id) => (int) $id !== (int) $keeper->id);

            if ($duplicateIds->isNotEmpty()) {
                DB::table('brief_submissions')
                    ->whereIn('brand_brief_form_id', $duplicateIds)
                    ->update(['brand_brief_form_id' => $keeper->id]);

                DB::table('brand_brief_forms')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            }
        }

        DB::table('brief_form_types')->update([
            'default_form_path' => '/brief-form',
            'updated_at'        => now(),
        ]);
    }

    public function down(): void
    {
        // Irreversible data merge.
    }

    private function briefFormScore(object $form): float
    {
        $schema = $form->schema ?? null;
        $hasSchema = is_string($schema)
            ? trim($schema) !== '' && trim($schema) !== '[]' && trim($schema) !== '{}'
            : ! empty($schema);

        return ($form->is_active ? 1000 : 0)
            + ($hasSchema ? 100 : 0)
            + (float) ($form->schema_version ?? 0)
            + ((int) $form->id) / 1_000_000;
    }
};
