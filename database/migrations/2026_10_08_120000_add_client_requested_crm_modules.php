<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'brand_id')) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('company_name');
            }
            if (! Schema::hasColumn('clients', 'website')) {
                $table->string('website')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('clients', 'customer_group')) {
                $table->string('customer_group')->nullable()->after('website');
            }
            if (! Schema::hasColumn('clients', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (! Schema::hasColumn('clients', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
            if (! Schema::hasColumn('clients', 'zip')) {
                $table->string('zip', 20)->nullable()->after('state');
            }
            if (! Schema::hasColumn('clients', 'country')) {
                $table->string('country')->nullable()->after('zip');
            }
            if (! Schema::hasColumn('clients', 'currency')) {
                $table->string('currency', 10)->default('USD')->after('country');
            }
            if (! Schema::hasColumn('clients', 'language')) {
                $table->string('language', 50)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('clients', 'billing_address')) {
                $table->text('billing_address')->nullable()->after('language');
            }
            if (! Schema::hasColumn('clients', 'shipping_address')) {
                $table->text('shipping_address')->nullable()->after('billing_address');
            }
            if (! Schema::hasColumn('clients', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('notes');
            }
        });

        if (! Schema::hasTable('client_contacts')) {
            Schema::create('client_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('position')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->index('client_id');
            });
        }

        DB::statement("ALTER TABLE leads MODIFY status VARCHAR(50) NOT NULL DEFAULT 'new'");

        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'company')) {
                $table->string('company')->nullable()->after('name');
            }
            if (! Schema::hasColumn('leads', 'position')) {
                $table->string('position')->nullable()->after('company');
            }
            if (! Schema::hasColumn('leads', 'website')) {
                $table->string('website')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('leads', 'address')) {
                $table->text('address')->nullable()->after('website');
            }
            if (! Schema::hasColumn('leads', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (! Schema::hasColumn('leads', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
            if (! Schema::hasColumn('leads', 'zip')) {
                $table->string('zip', 20)->nullable()->after('state');
            }
            if (! Schema::hasColumn('leads', 'country')) {
                $table->string('country')->nullable()->after('zip');
            }
            if (! Schema::hasColumn('leads', 'lead_value')) {
                $table->decimal('lead_value', 15, 2)->default(0)->after('country');
            }
            if (! Schema::hasColumn('leads', 'tags')) {
                $table->string('tags')->nullable()->after('lead_value');
            }
            if (! Schema::hasColumn('leads', 'last_contact_at')) {
                $table->date('last_contact_at')->nullable()->after('tags');
            }
            if (! Schema::hasColumn('leads', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('last_contact_at');
            }
            if (! Schema::hasColumn('leads', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('is_public');
            }
        });

        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('status', 40)->default('in_progress');
                $table->string('billing_type', 40)->default('fixed_rate');
                $table->decimal('total_rate', 15, 2)->default(0);
                $table->decimal('estimated_hours', 10, 2)->nullable();
                $table->unsignedTinyInteger('progress')->default(0);
                $table->boolean('progress_from_tasks')->default(true);
                $table->date('start_date')->nullable();
                $table->date('deadline')->nullable();
                $table->string('tags')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('client_id');
                $table->index('status');
                $table->index('team_id');
            });
        }

        if (! Schema::hasTable('project_user')) {
            Schema::create('project_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
                $table->unique(['project_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->id();
                $table->string('subject');
                $table->text('description')->nullable();
                $table->decimal('hourly_rate', 15, 2)->default(0);
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('priority', 20)->default('medium');
                $table->string('status', 40)->default('not_started');
                $table->boolean('is_public')->default(false);
                $table->boolean('is_billable')->default(true);
                $table->string('related_type', 40)->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->string('repeat_every', 40)->nullable();
                $table->string('tags')->nullable();
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['related_type', 'related_id']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('task_user')) {
            Schema::create('task_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id');
                $table->unsignedBigInteger('user_id');
                $table->string('relation', 20)->default('assignee');
                $table->timestamps();
                $table->unique(['task_id', 'user_id', 'relation']);
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->string('category');
                $table->decimal('amount', 15, 2);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->date('expense_date');
                $table->text('note')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->boolean('is_billable')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('expense_date');
                $table->index('category');
            });
        }

        DB::statement("ALTER TABLE invoices MODIFY sale_id BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE invoices MODIFY status ENUM('issued','void','draft') NOT NULL DEFAULT 'issued'");

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('sale_id');
            }
            if (! Schema::hasColumn('invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('issued_at');
            }
            if (! Schema::hasColumn('invoices', 'discount')) {
                $table->decimal('discount', 15, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('invoices', 'adjustment')) {
                $table->decimal('adjustment', 15, 2)->default(0)->after('discount');
            }
            if (! Schema::hasColumn('invoices', 'tax_total')) {
                $table->decimal('tax_total', 15, 2)->default(0)->after('adjustment');
            }
            if (! Schema::hasColumn('invoices', 'client_note')) {
                $table->text('client_note')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('invoices', 'terms')) {
                $table->text('terms')->nullable()->after('client_note');
            }
        });

        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->string('description');
                $table->text('long_description')->nullable();
                $table->decimal('qty', 12, 2)->default(1);
                $table->decimal('rate', 15, 2)->default(0);
                $table->decimal('tax_rate', 8, 2)->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->timestamps();
                $table->index('invoice_id');
            });
        }

        if (! Schema::hasTable('invoice_payments')) {
            Schema::create('invoice_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->decimal('amount', 15, 2);
                $table->date('paid_at');
                $table->string('payment_mode')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('invoice_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('client_contacts');
    }
};
