<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('brief_form_types')
            ->where('slug', 'website')
            ->update([
                'default_form_path' => '/website-brief',
                'updated_at'        => now(),
            ]);

        DB::table('brand_brief_forms')
            ->where('form_path', '/brief-form')
            ->update([
                'form_path'  => '/website-brief',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('brief_form_types')
            ->where('slug', 'website')
            ->update([
                'default_form_path' => '/brief-form',
                'updated_at'        => now(),
            ]);

        DB::table('brand_brief_forms')
            ->where('form_path', '/website-brief')
            ->update([
                'form_path'  => '/brief-form',
                'updated_at' => now(),
            ]);
    }
};
