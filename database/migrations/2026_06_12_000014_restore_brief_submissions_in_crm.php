<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('brief_submissions')) {
            return;
        }

        Schema::create('brief_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sale_id');
            $table->string('brief_type', 20);
            $table->string('form_path', 100);
            $table->json('data');
            $table->json('attachments')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['sale_id', 'brief_type']);
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_submissions');
    }
};
