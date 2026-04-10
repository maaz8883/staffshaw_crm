<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('approval_status', ['pending_approval', 'approved', 'rejected'])
                  ->default('pending_approval')
                  ->after('status');
            $table->text('approval_note')->nullable()->after('approval_status');
            $table->foreignId('approved_by')->nullable()->after('approval_note')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approval_status', 'approval_note', 'approved_by', 'approved_at']);
        });
    }
};
