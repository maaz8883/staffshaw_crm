<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "IT" role: runs accounts and setup — users (create / edit / ban, never Admins), brands,
 * registrations, settings, backups, activity logs. No sales data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('name', 'IT')->exists()) {
            DB::table('roles')->insert([
                'name' => 'IT',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep the role if users still have it; otherwise remove it
        $roleId = DB::table('roles')->where('name', 'IT')->value('id');
        if ($roleId && ! DB::table('users')->where('role_id', $roleId)->exists()) {
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
