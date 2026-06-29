<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $database = config('database.connections.orbit_brand.database', 'orbit_brand');

        DB::statement(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            str_replace('`', '``', $database)
        ));

        if (! Schema::connection('orbit_brand')->hasTable('brief_submissions')) {
            Schema::connection('orbit_brand')->create('brief_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('sale_id');
                $table->string('brief_type', 20);
                $table->string('form_path', 100);
                $table->json('data');
                $table->json('attachments')->nullable();
                $table->string('status', 20)->default('submitted');
                $table->timestamp('submitted_at')->nullable();
                $table->string('client_name')->nullable();
                $table->string('client_email')->nullable();
                $table->string('client_ip', 45)->nullable();
                $table->timestamps();

                $table->unique(['sale_id', 'brief_type']);
                $table->index('sale_id');
            });
        }

        if (Schema::hasTable('brief_submissions')) {
            $rows = DB::table('brief_submissions')->get();

            foreach ($rows as $row) {
                DB::connection('orbit_brand')->table('brief_submissions')->updateOrInsert(
                    [
                        'sale_id'    => $row->sale_id,
                        'brief_type' => $row->brief_type,
                    ],
                    [
                        'form_path'     => $row->form_path,
                        'data'          => $row->data,
                        'attachments'   => $row->attachments,
                        'status'        => $row->status,
                        'submitted_at'  => $row->submitted_at,
                        'client_name'   => $row->client_name,
                        'client_email'  => $row->client_email,
                        'client_ip'     => $row->client_ip,
                        'created_at'    => $row->created_at,
                        'updated_at'    => $row->updated_at,
                    ]
                );
            }

            Schema::drop('brief_submissions');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('brief_submissions')) {
            Schema::create('brief_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('sale_id');
                $table->string('brief_type', 20);
                $table->string('form_path', 100);
                $table->json('data');
                $table->json('attachments')->nullable();
                $table->string('status', 20)->default('submitted');
                $table->timestamp('submitted_at')->nullable();
                $table->string('client_name')->nullable();
                $table->string('client_email')->nullable();
                $table->string('client_ip', 45)->nullable();
                $table->timestamps();

                $table->unique(['sale_id', 'brief_type']);
                $table->index('sale_id');
            });
        }

        if (Schema::connection('orbit_brand')->hasTable('brief_submissions')) {
            $rows = DB::connection('orbit_brand')->table('brief_submissions')->get();

            foreach ($rows as $row) {
                DB::table('brief_submissions')->updateOrInsert(
                    [
                        'sale_id'    => $row->sale_id,
                        'brief_type' => $row->brief_type,
                    ],
                    [
                        'form_path'     => $row->form_path,
                        'data'          => $row->data,
                        'attachments'   => $row->attachments,
                        'status'        => $row->status,
                        'submitted_at'  => $row->submitted_at,
                        'client_name'   => $row->client_name,
                        'client_email'  => $row->client_email,
                        'client_ip'     => $row->client_ip,
                        'created_at'    => $row->created_at,
                        'updated_at'    => $row->updated_at,
                    ]
                );
            }

            Schema::connection('orbit_brand')->drop('brief_submissions');
        }
    }
};
