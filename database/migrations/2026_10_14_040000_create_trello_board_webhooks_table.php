<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_board_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->string('board_id', 64);
            $table->string('webhook_id', 64)->nullable();
            $table->string('secret', 64);
            $table->boolean('active')->default(true);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'board_id']);
            $table->index('board_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_board_webhooks');
    }
};
