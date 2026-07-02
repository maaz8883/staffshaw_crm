<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brief_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_brief_form_id')->nullable()->after('sale_id');
            $table->index('brand_brief_form_id');
            $table->unique(['sale_id', 'brand_brief_form_id'], 'brief_submissions_sale_form_unique');
        });
    }

    public function down(): void
    {
        Schema::table('brief_submissions', function (Blueprint $table) {
            $table->dropUnique('brief_submissions_sale_form_unique');
            $table->dropIndex(['brand_brief_form_id']);
            $table->dropColumn('brand_brief_form_id');
        });
    }
};
