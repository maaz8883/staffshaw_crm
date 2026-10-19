<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "HR" role: people and day-to-day costs — users (create / edit / ban, never Admins)
 * and daily expenses (add / edit). No sales, brands or settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('name', 'HR')->exists()) {
            DB::table('roles')->insert([
                'name' => 'HR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep the role if users still have it; otherwise remove it
        $roleId = DB::table('roles')->where('name', 'HR')->value('id');
        if ($roleId && ! DB::table('users')->where('role_id', $roleId)->exists()) {
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
