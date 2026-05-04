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
        Schema::create('sub_team_head_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_team_head_id')->constrained('sub_team_heads')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->integer('month'); // 1-12
            $table->integer('year');  // e.g. 2026
            $table->decimal('target_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Unique constraint: one target per sub-team head per month/year
            $table->unique(['sub_team_head_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_team_head_targets');
    }
};
