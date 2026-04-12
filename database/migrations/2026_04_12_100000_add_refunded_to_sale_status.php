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
            $table->string('status_before_refund', 32)->nullable()->after('status');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('pending','completed','cancelled','refunded') NOT NULL DEFAULT 'completed'");
        }

        $rows = DB::table('sales')->where('is_refunded', true)->where('status', '!=', 'refunded')->get();
        foreach ($rows as $row) {
            DB::table('sales')->where('id', $row->id)->update([
                'status_before_refund' => in_array($row->status, ['pending', 'completed', 'cancelled'], true)
                    ? $row->status
                    : 'completed',
                'status' => 'refunded',
            ]);
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (DB::table('sales')->where('status', 'refunded')->get() as $row) {
            DB::table('sales')->where('id', $row->id)->update([
                'status' => $row->status_before_refund ?? 'completed',
                'status_before_refund' => null,
            ]);
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('status_before_refund');
        });

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'completed'");
        }
    }
};
