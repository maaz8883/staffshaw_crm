<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('parent_team_id')->nullable()->after('company_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('sub_team_head_id')->nullable()->after('team_head_id')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['parent_team_id']);
            $table->dropForeign(['sub_team_head_id']);
            $table->dropColumn(['parent_team_id', 'sub_team_head_id']);
        });
    }
};
