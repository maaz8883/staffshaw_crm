<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'short_code')) {
                $table->string('short_code', 32)->nullable()->after('name');
                $table->unique('short_code');
            }
        });

        // Starter codes for common master-sheet abbreviations (editable in Brands → Edit).
        $defaults = [
            'hamptonpublishers' => 'HP',
            'theglobalpublishers' => 'TGP',
            'waltzpublisher' => 'WP',
            'amazingpublishers' => 'Amazing',
            'amazonkdpservices' => 'KDP',
            'credenzamediagroup' => 'CMG',
            'hamfordpublishers' => 'HFP',
        ];

        foreach ($defaults as $name => $code) {
            DB::table('brands')
                ->whereRaw('LOWER(name) = ?', [$name])
                ->whereNull('short_code')
                ->update(['short_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'short_code')) {
                $table->dropUnique(['short_code']);
                $table->dropColumn('short_code');
            }
        });
    }
};
