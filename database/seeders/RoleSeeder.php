<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([Role::ADMIN, Role::MANAGER, Role::AGENT] as $roleName) {
            Role::query()->updateOrCreate(
                ['name' => $roleName],
                ['name' => $roleName]
            );
        }
    }
}
