<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $websiteTypeId = DB::table('brief_form_types')
            ->where('slug', 'website')
            ->value('id');

        if (! $websiteTypeId) {
            return;
        }

        $brands = DB::table('brands')
            ->whereNotNull('brief_document')
            ->get(['id', 'brief_document', 'brief_document_name']);

        foreach ($brands as $brand) {
            DB::table('brand_brief_forms')->insert([
                'brand_id'            => $brand->id,
                'brief_form_type_id'  => $websiteTypeId,
                'name'                => 'Website',
                'form_path'           => '/brief-form',
                'document'            => $brand->brief_document,
                'document_name'       => $brand->brief_document_name,
                'sort_order'          => 0,
                'is_active'           => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    public function down(): void
    {
        $websiteTypeId = DB::table('brief_form_types')
            ->where('slug', 'website')
            ->value('id');

        if (! $websiteTypeId) {
            return;
        }

        $forms = DB::table('brand_brief_forms')
            ->where('brief_form_type_id', $websiteTypeId)
            ->where('form_path', '/brief-form')
            ->get();

        foreach ($forms as $form) {
            if ($form->document) {
                DB::table('brands')
                    ->where('id', $form->brand_id)
                    ->update([
                        'brief_document'      => $form->document,
                        'brief_document_name' => $form->document_name,
                    ]);
            }
        }

        DB::table('brand_brief_forms')
            ->where('brief_form_type_id', $websiteTypeId)
            ->where('form_path', '/brief-form')
            ->delete();
    }
};
