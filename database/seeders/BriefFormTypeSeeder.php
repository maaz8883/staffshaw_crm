<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BriefFormTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Website', 'slug' => 'website', 'default_form_path' => '/website-brief', 'sort_order' => 0],
            ['name' => 'Logo', 'slug' => 'logo', 'default_form_path' => '/logo-brief', 'sort_order' => 1],
            ['name' => 'Ebook', 'slug' => 'ebook', 'default_form_path' => '/ebook-brief', 'sort_order' => 2],
        ];

        foreach ($types as $type) {
            DB::table('brief_form_types')->updateOrInsert(
                ['slug' => $type['slug']],
                array_merge($type, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
