<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Map legacy / duplicate statuses onto the short agent-friendly set.
        $map = [
            'new' => 'new_lead',
            'won' => 'customer',
            'low_funds' => 'follow_up',
            'not_connected' => 'follow_up',
        ];

        foreach ($map as $from => $to) {
            DB::table('leads')->where('status', $from)->update(['status' => $to]);
        }

        DB::statement("ALTER TABLE leads MODIFY status VARCHAR(50) NOT NULL DEFAULT 'new_lead'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE leads MODIFY status VARCHAR(50) NOT NULL DEFAULT 'new'");
    }
};
