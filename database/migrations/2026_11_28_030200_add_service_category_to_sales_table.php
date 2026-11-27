<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'service_category')) {
                $table->string('service_category', 20)->nullable()->after('sale_type');
                $table->index('service_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'service_category')) {
                $table->dropIndex(['service_category']);
                $table->dropColumn('service_category');
            }
        });
    }
};
