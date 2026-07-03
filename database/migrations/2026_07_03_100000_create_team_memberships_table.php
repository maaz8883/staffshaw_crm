<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Seed the Project Manager role for existing installs (idempotent).
        DB::table('roles')->insertOrIgnore([
            'name'       => 'Project Manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Note: users/teams are MyISAM in this database, so InnoDB foreign key
        // constraints (which require the referenced table to also be InnoDB)
        // cannot be used here. Plain indexed columns are used instead, matching
        // the pattern already tolerated elsewhere in this schema.
        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('team_id');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'team_id']);
            $table->index(['team_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_memberships');
    }
};
