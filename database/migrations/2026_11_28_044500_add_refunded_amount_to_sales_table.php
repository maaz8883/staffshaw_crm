<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'refunded_amount')) {
                $table->decimal('refunded_amount', 12, 2)->nullable()->after('refunded_by');
            }
        });

        // Existing full refunds: treat refunded amount as the sale total.
        if (Schema::hasColumn('sales', 'refunded_amount')) {
            DB::table('sales')
                ->where('is_refunded', true)
                ->whereNull('refunded_amount')
                ->update(['refunded_amount' => DB::raw('amount')]);
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'refunded_amount')) {
                $table->dropColumn('refunded_amount');
            }
        });
    }
};
