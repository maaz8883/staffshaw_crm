<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No DB FKs — users/teams/brands may be MyISAM (same pattern as team_memberships).
        Schema::create('brand_team', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('team_id');
            $table->timestamps();

            $table->unique(['brand_id', 'team_id']);
            $table->index('team_id');
            $table->index('brand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_team');
    }
};
