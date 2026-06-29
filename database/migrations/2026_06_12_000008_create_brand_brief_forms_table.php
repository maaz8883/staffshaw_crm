<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_brief_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('brief_form_type_id')->nullable();
            $table->string('name');
            $table->string('form_path');
            $table->string('document')->nullable();
            $table->string('document_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('brand_id');
            $table->index('brief_form_type_id');
            $table->unique(['brand_id', 'form_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_brief_forms');
    }
};
