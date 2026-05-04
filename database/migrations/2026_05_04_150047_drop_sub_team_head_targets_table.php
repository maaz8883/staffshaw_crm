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
        Schema::dropIfExists('sub_team_head_targets');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('sub_team_head_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_team_head_id')->constrained('sub_team_heads')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->integer('month');
            $table->integer('year');
            $table->decimal('target_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['sub_team_head_id', 'month', 'year']);
        });
    }
};
