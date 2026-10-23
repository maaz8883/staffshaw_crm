<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A sales team can have several team leads, all with the same rights.
 * teams.team_head_id stays as the first lead (older screens and reports read it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_heads', function (Blueprint $table) {
            $table->id();
            // No foreign keys: teams / users are MyISAM on some servers (cleaned up in Team / User code instead)
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        // Every team's current lead becomes its first entry
        $now = now();
        $rows = DB::table('teams')->whereNotNull('team_head_id')->get(['id', 'team_head_id'])
            ->map(fn ($t) => ['team_id' => $t->id, 'user_id' => $t->team_head_id, 'created_at' => $now, 'updated_at' => $now])
            ->all();
        if ($rows) {
            DB::table('team_heads')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_heads');
    }
};
