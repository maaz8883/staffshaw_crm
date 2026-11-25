<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Imported sales may have no matched Sales Agent; assign later in CRM.
        DB::statement('ALTER TABLE `sales` MODIFY `user_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `sales` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');
    }
};
