<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only event log read by CompanyOS (GET /api/v1/service/events?after=<id>).
 * Rows are never updated; CompanyOS keeps its own cursor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outbox_events')) {
            return;
        }

        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('aggregate_type', 32);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('context', 32)->default('live');
            $table->json('payload');
            $table->timestamp('occurred_at')->useCurrent();

            $table->index('type');
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
