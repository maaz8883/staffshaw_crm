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
            if (! Schema::hasColumn('sales', 'is_chargeback')) {
                $table->boolean('is_chargeback')->default(false)->after('is_refunded');
            }
            if (! Schema::hasColumn('sales', 'chargeback_at')) {
                $table->timestamp('chargeback_at')->nullable()->after('is_chargeback');
            }
            if (! Schema::hasColumn('sales', 'chargeback_by')) {
                $table->foreignId('chargeback_by')->nullable()->after('chargeback_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales', 'status_before_chargeback')) {
                $table->string('status_before_chargeback', 30)->nullable()->after('status_before_refund');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('pending','completed','cancelled','refunded','chargeback') NOT NULL DEFAULT 'completed'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('sales')->where('status', 'chargeback')->update(['status' => 'completed']);
            DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('pending','completed','cancelled','refunded') NOT NULL DEFAULT 'completed'");
        }

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'chargeback_by')) {
                $table->dropForeign(['chargeback_by']);
            }
            foreach (['status_before_chargeback', 'chargeback_by', 'chargeback_at', 'is_chargeback'] as $col) {
                if (Schema::hasColumn('sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
