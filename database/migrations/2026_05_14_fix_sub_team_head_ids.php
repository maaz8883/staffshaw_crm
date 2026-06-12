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
        // Fix sub_team_head_id references dynamically
        // Find all sub-team heads and update users to match their actual IDs
        
        $subTeamHeads = DB::table('sub_team_heads')->get();
        
        foreach ($subTeamHeads as $subHead) {
            // Update the sub-team head user themselves
            DB::table('users')
                ->where('id', $subHead->user_id)
                ->update(['sub_team_head_id' => $subHead->id]);
            
            // Note: Other users under this sub-team head should already have the correct ID
            // or need to be manually assigned through the UI
        }
        
        // Log for debugging
        \Log::info('Sub-team head IDs fixed', [
            'sub_team_heads' => $subTeamHeads->pluck('id', 'user_id')->toArray()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the changes
        DB::table('users')
            ->where('sub_team_head_id', 3)
            ->update(['sub_team_head_id' => 1]);
        
        DB::table('users')
            ->where('sub_team_head_id', 4)
            ->update(['sub_team_head_id' => 2]);
    }
};
