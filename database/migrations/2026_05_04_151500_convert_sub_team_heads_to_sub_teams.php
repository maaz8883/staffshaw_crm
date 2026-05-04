<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert each sub-team head into an actual sub-team
        $subTeamHeads = DB::table('sub_team_heads')->get();
        
        foreach ($subTeamHeads as $subHead) {
            // Create a new team (sub-team) for each sub-team head
            $subTeamId = DB::table('teams')->insertGetId([
                'company_id' => DB::table('teams')->where('id', $subHead->team_id)->value('company_id'),
                'parent_team_id' => $subHead->team_id,
                'team_head_id' => $subHead->user_id,
                'name' => $subHead->title,
                'description' => 'Converted from Sub-Team Head',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Update users who were under this sub-team head to be in the new sub-team
            DB::table('users')
                ->where('sub_team_head_id', $subHead->id)
                ->update([
                    'team_id' => $subTeamId,
                    'sub_team_id' => $subTeamId,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a data migration, reverting would be complex
        // We'll just delete the sub-teams created by this migration
        DB::table('teams')->whereNotNull('parent_team_id')->delete();
    }
};
