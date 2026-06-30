<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->string('invoice_number')->unique();
            $table->date('issued_at');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone', 50)->nullable();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('team_name')->nullable();
            $table->string('company_name')->nullable();
            $table->decimal('sale_total', 15, 2);
            $table->decimal('sale_received', 15, 2)->default(0);
            $table->decimal('sale_balance', 15, 2)->default(0);
            $table->enum('status', ['issued', 'void'])->default('issued');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index('sale_id');
            $table->index('issued_at');
            $table->index('brand_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
