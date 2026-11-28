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
            if (! Schema::hasColumn('sales', 'chargeback_amount')) {
                $table->decimal('chargeback_amount', 12, 2)->nullable()->after('chargeback_by');
            }
        });

        if (Schema::hasColumn('sales', 'chargeback_amount')) {
            DB::table('sales')
                ->where('is_chargeback', true)
                ->whereNull('chargeback_amount')
                ->update(['chargeback_amount' => DB::raw('amount')]);
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'chargeback_amount')) {
                $table->dropColumn('chargeback_amount');
            }
        });
    }
};
