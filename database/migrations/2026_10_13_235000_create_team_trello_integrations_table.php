<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // teams/users are MyISAM in this DB — skip FK constraints.
        Schema::create('team_trello_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->unique();
            $table->text('api_key');
            $table->text('api_token');
            $table->string('account_email')->nullable();
            $table->string('trello_member_id')->nullable();
            $table->string('trello_username')->nullable();
            $table->string('trello_full_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('connected_by')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index('connected_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_trello_integrations');
    }
};
