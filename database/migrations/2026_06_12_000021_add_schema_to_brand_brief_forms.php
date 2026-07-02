<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_brief_forms', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('form_path');
            $table->json('schema')->nullable()->after('slug');
            $table->unsignedTinyInteger('schema_version')->default(1)->after('schema');
        });

        foreach (DB::table('brand_brief_forms')->get() as $row) {
            $slug = null;

            if ($row->brief_form_type_id) {
                $slug = DB::table('brief_form_types')->where('id', $row->brief_form_type_id)->value('slug');
            }

            if (! $slug) {
                $path = trim((string) $row->form_path, '/');
                $slug = preg_replace('/-brief$/', '', $path) ?: Str::slug((string) $row->name);
            }

            DB::table('brand_brief_forms')->where('id', $row->id)->update([
                'slug' => $slug,
            ]);
        }

        Schema::table('brand_brief_forms', function (Blueprint $table) {
            $table->unique(['brand_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('brand_brief_forms', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'slug']);
            $table->dropColumn(['slug', 'schema', 'schema_version']);
        });
    }
};
