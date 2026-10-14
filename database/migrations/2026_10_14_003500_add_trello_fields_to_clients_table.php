<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'trello_card_id')) {
                $table->string('trello_card_id', 64)->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('clients', 'trello_board_id')) {
                $table->string('trello_board_id', 64)->nullable()->after('trello_card_id');
            }
            if (! Schema::hasColumn('clients', 'trello_list_id')) {
                $table->string('trello_list_id', 64)->nullable()->after('trello_board_id');
            }
            if (! Schema::hasColumn('clients', 'trello_board_name')) {
                $table->string('trello_board_name')->nullable()->after('trello_list_id');
            }
            if (! Schema::hasColumn('clients', 'trello_list_name')) {
                $table->string('trello_list_name')->nullable()->after('trello_board_name');
            }
            if (! Schema::hasColumn('clients', 'trello_card_url')) {
                $table->string('trello_card_url', 500)->nullable()->after('trello_list_name');
            }
            if (! Schema::hasColumn('clients', 'trello_synced_at')) {
                $table->timestamp('trello_synced_at')->nullable()->after('trello_card_url');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('trello_card_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'trello_card_id')) {
                $table->dropIndex(['trello_card_id']);
            }
            foreach ([
                'trello_card_id', 'trello_board_id', 'trello_list_id',
                'trello_board_name', 'trello_list_name', 'trello_card_url', 'trello_synced_at',
            ] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
