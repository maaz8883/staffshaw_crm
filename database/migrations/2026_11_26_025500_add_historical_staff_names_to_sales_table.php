<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'sales_agent_name')) {
                $table->string('sales_agent_name', 255)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('sales', 'account_manager_name')) {
                $table->string('account_manager_name', 255)->nullable()->after('account_manager_id');
            }
            if (! Schema::hasColumn('sales', 'project_manager_name')) {
                $table->string('project_manager_name', 255)->nullable()->after('project_manager_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            foreach (['sales_agent_name', 'account_manager_name', 'project_manager_name'] as $col) {
                if (Schema::hasColumn('sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
