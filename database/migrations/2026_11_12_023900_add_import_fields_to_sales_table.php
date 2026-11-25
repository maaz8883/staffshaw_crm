<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('merchant', 120)->nullable()->after('notes');
            $table->boolean('is_ppc')->default(false)->after('merchant');
            $table->unsignedBigInteger('account_manager_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('project_manager_id')->nullable()->after('account_manager_id');

            $table->index('merchant');
            $table->index('is_ppc');
            $table->index('account_manager_id');
            $table->index('project_manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['merchant']);
            $table->dropIndex(['is_ppc']);
            $table->dropIndex(['account_manager_id']);
            $table->dropIndex(['project_manager_id']);
            $table->dropColumn([
                'merchant',
                'is_ppc',
                'account_manager_id',
                'project_manager_id',
            ]);
        });
    }
};
