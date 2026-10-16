<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production teams (Trello work only) are kept out of sales pages: targets, reports, dropdowns…
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_production')->default(false)->after('parent_team_id');
        });

        // Existing production structure: main teams named "Production…" and all their sub-teams
        $mainIds = DB::table('teams')->whereNull('parent_team_id')
            ->whereRaw("LOWER(name) LIKE 'production%'")->pluck('id');
        if ($mainIds->isNotEmpty()) {
            DB::table('teams')->whereIn('id', $mainIds)->orWhereIn('parent_team_id', $mainIds)
                ->update(['is_production' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_production');
        });
    }
};
