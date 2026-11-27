<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Lead;
use App\Models\OutboxEvent;
use App\Models\Sale;
use App\Models\Team;
use App\Models\TeamPpcSpending;
use App\Support\Outbox;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanyOsServiceApiTest extends TestCase
{
    private const KEY = 'companyos-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.companyos.service_key' => self::KEY,
        ]);
        DB::purge('sqlite');
        $this->createSchema();
        Carbon::setTestNow('2026-11-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── authentication ───────────────────────────────────────────────────────

    public function test_missing_key_is_rejected(): void
    {
        $this->getJson('/api/v1/service/summary')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_wrong_key_is_rejected(): void
    {
        $this->getJson('/api/v1/service/summary', ['X-Service-Key' => 'wrong'])->assertStatus(401);
    }

    public function test_bearer_token_is_accepted(): void
    {
        $this->getJson('/api/v1/service/summary', ['Authorization' => 'Bearer '.self::KEY])->assertOk();
    }

    public function test_unconfigured_key_returns_503(): void
    {
        config(['services.companyos.service_key' => '']);

        $this->getJson('/api/v1/service/summary', ['X-Service-Key' => 'anything'])->assertStatus(503);
    }

    public function test_invalid_dates_return_422_json(): void
    {
        $this->getJson('/api/v1/service/summary?from=15-11-2026', $this->auth())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->getJson('/api/v1/service/summary?from=2026-11-20&to=2026-11-01', $this->auth())
            ->assertStatus(422);
    }

    // ── summary ──────────────────────────────────────────────────────────────

    public function test_summary_matches_finance_definitions(): void
    {
        $team = Team::create(['name' => 'Alpha']);

        // Counted: approved, not refunded, in November
        $this->sale(['amount' => 1000, 'received_amount' => 400, 'team_id' => $team->id]);
        $this->sale(['amount' => 500, 'received_amount' => 500, 'team_id' => $team->id, 'status' => 'pending']);
        // Not counted in booked: awaiting approval, refunded, other month
        $this->sale(['amount' => 9999, 'approval_status' => Sale::APPROVAL_PENDING]);
        $this->sale(['amount' => 300, 'is_refunded' => true, 'refunded_at' => '2026-11-10 12:00:00', 'status' => Sale::STATUS_REFUNDED]);
        $this->sale(['amount' => 700, 'sale_date' => '2026-10-20']);

        // Sale-linked receipt (counts as received) + standalone invoice with a partial payment (overdue)
        Invoice::create(['sale_id' => 1, 'invoice_number' => 'R-1', 'amount' => 400, 'issued_at' => '2026-11-03', 'status' => Invoice::STATUS_ISSUED, 'client_name' => 'A']);
        $open = Invoice::create(['invoice_number' => 'INV-1', 'amount' => 800, 'issued_at' => '2026-11-01', 'due_date' => '2026-11-10', 'status' => Invoice::STATUS_ISSUED, 'client_name' => 'B']);
        InvoicePayment::create(['invoice_id' => $open->id, 'amount' => 200, 'paid_at' => '2026-11-05']);

        Expense::create(['category' => 'Utilities', 'amount' => 15000, 'expense_date' => '2026-11-02']);
        TeamPpcSpending::create(['team_id' => $team->id, 'month' => 11, 'year' => 2026, 'amount' => 250]);
        Lead::create(['name' => 'Lead A', 'status' => 'new_lead', 'source' => 'website']);

        $data = $this->getJson('/api/v1/service/summary?from=2026-11-01&to=2026-11-30', $this->auth())
            ->assertOk()
            ->json('data');

        $this->assertSame(1500.0, (float) $data['sales']['booked']);
        $this->assertSame(2, $data['sales']['count']);
        $this->assertSame(1000.0, (float) $data['sales']['completed_revenue']);
        $this->assertSame(600.0, (float) $data['sales']['received']);
        $this->assertSame(300.0, (float) $data['sales']['refunds_total']);
        $this->assertSame(1, $data['sales']['awaiting_approval_count']);
        $this->assertSame(250.0, (float) $data['ppc']['spend']);
        $this->assertSame(350.0, (float) $data['ppc']['net_cash']);
        $this->assertSame(1, $data['receivables']['open_invoices_count']);
        $this->assertSame(600.0, (float) $data['receivables']['open_invoices_amount']);
        $this->assertSame(1, $data['receivables']['overdue_invoices_count']);
        $this->assertSame(15000.0, (float) $data['expenses']['total']);
        $this->assertSame(1, $data['leads']['new_count']);
        $this->assertSame('Alpha', $data['by_team'][0]['team_name']);
        $this->assertSame(1500.0, (float) $data['by_team'][0]['booked']);
    }

    public function test_unpaid_invoices_list_only_open_standalone_invoices(): void
    {
        Invoice::create(['sale_id' => 1, 'invoice_number' => 'R-1', 'amount' => 400, 'issued_at' => '2026-11-03', 'status' => Invoice::STATUS_ISSUED, 'client_name' => 'A']);
        Invoice::create(['invoice_number' => 'PAID', 'amount' => 100, 'issued_at' => '2026-11-01', 'status' => Invoice::STATUS_ISSUED, 'client_name' => 'B']);
        InvoicePayment::create(['invoice_id' => 2, 'amount' => 100, 'paid_at' => '2026-11-02']);
        Invoice::create(['invoice_number' => 'OPEN', 'amount' => 300, 'issued_at' => '2026-11-01', 'status' => Invoice::STATUS_ISSUED, 'client_name' => 'C']);

        $this->getJson('/api/v1/service/invoices?status=unpaid', $this->auth())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'OPEN')
            ->assertJsonPath('meta.total', 1);
    }

    // ── events ───────────────────────────────────────────────────────────────

    public function test_sale_lifecycle_records_events(): void
    {
        $sale = $this->sale(['approval_status' => Sale::APPROVAL_PENDING]);
        $sale->update(['approval_status' => Sale::APPROVAL_APPROVED]);
        $sale->update(['is_refunded' => true, 'refunded_at' => now()]);

        $this->assertSame(
            ['sale.submitted', 'sale.approved', 'sale.refunded'],
            OutboxEvent::query()->orderBy('id')->pluck('type')->all()
        );
        $this->assertSame(1000.0, (float) OutboxEvent::query()->first()->payload['amount']);
    }

    public function test_draft_sale_records_event_only_when_submitted(): void
    {
        $sale = $this->sale(['is_draft' => true, 'approval_status' => Sale::APPROVAL_PENDING]);
        $this->assertSame(0, OutboxEvent::query()->count());

        $sale->update(['is_draft' => false]);
        $this->assertSame(['sale.submitted'], OutboxEvent::query()->pluck('type')->all());
    }

    public function test_lead_and_payment_events(): void
    {
        $lead = Lead::create(['name' => 'Lead A', 'status' => 'new_lead', 'source' => 'website']);
        $lead->update(['status' => 'contacted']);
        $invoice = Invoice::create(['invoice_number' => 'INV-9', 'amount' => 100, 'status' => Invoice::STATUS_ISSUED, 'client_name' => 'C']);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 60, 'paid_at' => '2026-11-14']);

        $types = OutboxEvent::query()->orderBy('id')->pluck('type')->all();
        $this->assertSame(['lead.created', 'lead.status_changed', 'invoice.payment_received'], $types);

        $payment = OutboxEvent::query()->where('type', 'invoice.payment_received')->first();
        $this->assertSame('INV-9', $payment->payload['invoice_number']);
        $this->assertSame(60.0, (float) $payment->payload['amount']);
    }

    public function test_import_context_is_recorded(): void
    {
        Outbox::withContext('import', fn () => Lead::create(['name' => 'Old lead', 'status' => 'new_lead']));
        Lead::create(['name' => 'New lead', 'status' => 'new_lead']);

        $this->assertSame(['import', 'live'], OutboxEvent::query()->orderBy('id')->pluck('context')->all());
    }

    public function test_events_endpoint_pages_with_cursor(): void
    {
        foreach (range(1, 3) as $i) {
            Lead::create(['name' => "Lead {$i}", 'status' => 'new_lead']);
        }

        $first = $this->getJson('/api/v1/service/events?after=0&limit=2', $this->auth())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.source', 'crm')
            ->assertJsonPath('meta.has_more', true);

        $next = $first->json('meta.next_after');

        $this->getJson("/api/v1/service/events?after={$next}&limit=2", $this->auth())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_bad_legacy_date_does_not_break_saving(): void
    {
        $lead = Lead::create(['name' => 'Legacy', 'status' => 'new_lead']);
        // Simulate a legacy row whose follow-up date cannot be parsed
        DB::table('leads')->where('id', $lead->id)->update(['follow_up_at' => 'not-a-date']);

        $legacy = Lead::query()->find($lead->id);
        $legacy->status = 'contacted';
        $legacy->save();

        $this->assertSame('contacted', DB::table('leads')->where('id', $lead->id)->value('status'));
    }

    public function test_missing_outbox_table_does_not_break_saving(): void
    {
        Schema::drop('outbox_events');

        $lead = Lead::create(['name' => 'Still saved', 'status' => 'new_lead']);

        $this->assertNotNull($lead->id);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function auth(): array
    {
        return ['X-Service-Key' => self::KEY];
    }

    private function sale(array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'title' => 'Website',
            'client_name' => 'Acme',
            'amount' => 1000,
            'received_amount' => 0,
            'sale_date' => '2026-11-05',
            'status' => 'completed',
            'sale_type' => 'front',
            'approval_status' => Sale::APPROVAL_APPROVED,
            'is_refunded' => false,
            'is_draft' => false,
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('client_name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('received_amount', 15, 2)->default(0);
            $table->date('sale_date')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('sales_agent_name')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('status')->default('completed');
            $table->string('sale_type')->default('front');
            $table->string('approval_status')->default('pending_approval');
            $table->boolean('is_refunded')->default(false);
            $table->timestamp('refunded_at')->nullable();
            $table->boolean('is_ppc')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->timestamps();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new_lead');
            $table->decimal('lead_value', 15, 2)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->date('follow_up_at')->nullable();
            $table->date('last_contact_at')->nullable();
            $table->timestamps();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('invoice_number');
            $table->date('issued_at')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency')->nullable();
            $table->string('client_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('team_name')->nullable();
            $table->string('status')->default('issued');
            $table->timestamps();
        });
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('amount', 15, 2);
            $table->date('paid_at')->nullable();
            $table->string('payment_mode')->nullable();
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->date('expense_date');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->timestamps();
        });
        Schema::create('team_ppc_spendings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        (require database_path('migrations/2026_11_27_100000_create_outbox_events_table.php'))->up();
    }
}
