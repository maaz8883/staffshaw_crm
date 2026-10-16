<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Production" role for production teams (Design, Development, SEO, SMM, Writers, Ebook):
 * Trello for their own team + a production dashboard, no sales / leads / invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('name', 'Production')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Production',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep the role if users still have it; otherwise remove it
        $roleId = DB::table('roles')->where('name', 'Production')->value('id');
        if ($roleId && ! DB::table('users')->where('role_id', $roleId)->exists()) {
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
