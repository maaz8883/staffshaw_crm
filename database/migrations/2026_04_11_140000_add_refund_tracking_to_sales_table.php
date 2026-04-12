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
            $table->boolean('is_refunded')->default(false)->after('sale_type');
            $table->timestamp('refunded_at')->nullable()->after('is_refunded');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
        });

        // Old "refund" sale type → front + refunded flag
        DB::table('sales')->where('sale_type', 'refund')->update([
            'sale_type'   => 'front',
            'is_refunded' => true,
            'refunded_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropColumn(['is_refunded', 'refunded_at', 'refunded_by']);
        });
    }
};
