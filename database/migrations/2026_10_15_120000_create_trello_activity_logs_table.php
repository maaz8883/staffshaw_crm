<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who did what on Trello through the CRM. Teams share one Trello account, so Trello itself
 * can't tell people apart — the CRM records the logged-in user for every change it sends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_activity_logs', function (Blueprint $table) {
            $table->id();
            // Indexed ids, no DB foreign keys: the history must outlive deleted users/teams,
            // and some installs still have MyISAM users/teams tables (no FK support)
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->string('board_id', 64)->nullable()->index();
            $table->string('card_id', 64)->nullable()->index();
            $table->string('card_name')->nullable();
            $table->string('action', 50)->index();       // card_moved, comment_added, label_added…
            $table->string('description', 1000);
            $table->string('trello_action_id', 64)->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->index();     // when the user did it (not when it was written)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_activity_logs');
    }
};
