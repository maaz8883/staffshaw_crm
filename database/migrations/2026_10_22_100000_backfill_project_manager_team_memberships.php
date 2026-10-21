<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Project Managers put in a team from Users → Edit (team_id set) never got the membership that
 * PM access relies on, so they saw "No teams joined" and didn't show on the team. Approve the
 * membership for that team. Nothing else is touched (other teams they joined stay as they are).
 */
return new class extends Migration
{
    public function up(): void
    {
        $pmRoleId = DB::table('roles')->where('name', 'Project Manager')->value('id');
        if (! $pmRoleId) {
            return;
        }

        // Only teams that still exist (live data may have stale team_id values)
        $pms = DB::table('users')
            ->join('teams', 'teams.id', '=', 'users.team_id')
            ->where('users.role_id', $pmRoleId)
            ->get(['users.id', 'users.team_id']);
        foreach ($pms as $pm) {
            $existing = DB::table('team_memberships')->where('user_id', $pm->id)->where('team_id', $pm->team_id)->first();
            if ($existing && $existing->status === 'approved') {
                continue;
            }
            if ($existing) {
                DB::table('team_memberships')->where('id', $existing->id)->update(['status' => 'approved', 'decided_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('team_memberships')->insert([
                    'user_id' => $pm->id,
                    'team_id' => $pm->team_id,
                    'status' => 'approved',
                    'decided_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Memberships are real data now; nothing to undo safely.
    }
};
