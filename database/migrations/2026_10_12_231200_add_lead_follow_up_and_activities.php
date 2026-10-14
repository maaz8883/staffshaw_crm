<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'follow_up_at')) {
                $table->date('follow_up_at')->nullable()->after('last_contact_at');
                $table->index('follow_up_at');
            }
        });

        if (! Schema::hasTable('lead_activities')) {
            Schema::create('lead_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lead_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type', 40)->default('note'); // note | follow_up | contact
                $table->text('body');
                $table->timestamps();

                $table->index('lead_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'follow_up_at')) {
                $table->dropIndex(['follow_up_at']);
                $table->dropColumn('follow_up_at');
            }
        });
    }
};
