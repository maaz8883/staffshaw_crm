<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_brief_forms', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'form_path']);
        });
    }

    public function down(): void
    {
        Schema::table('brand_brief_forms', function (Blueprint $table) {
            $table->unique(['brand_id', 'form_path']);
        });
    }
};
