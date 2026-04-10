<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = ['StaffShaw', 'DSS'];

        foreach ($companies as $name) {
            Company::query()->updateOrCreate(
                ['name' => $name],
                ['name' => $name]
            );
        }
    }
}
