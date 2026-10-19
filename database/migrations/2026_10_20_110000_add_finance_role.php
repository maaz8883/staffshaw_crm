<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Finance" role: company-wide money — invoices, payments, receipts, refunds, expenses,
 * financial reports, PPC spend (view), sales and clients read-only. No leads or setup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('name', 'Finance')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Finance',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep the role if users still have it; otherwise remove it
        $roleId = DB::table('roles')->where('name', 'Finance')->value('id');
        if ($roleId && ! DB::table('users')->where('role_id', $roleId)->exists()) {
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
