<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandBriefForm;
use App\Models\BriefFormType;
use App\Models\BriefSubmission;
use App\Models\Client;
use App\Models\Sale;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientBriefApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.orbit_brief.api_key' => 'brief-test-key',
        ]);
        DB::purge('sqlite');
        $this->createSchema();
    }

    public function test_it_returns_all_briefs_from_all_sales_for_a_client(): void
    {
        $client = Client::create([
            'name' => 'Acme Client',
            'email' => 'acme@example.com',
        ]);
        $websiteBrand = Brand::create(['name' => 'Web Brand', 'website' => 'https://web.test']);
        $logoBrand = Brand::create(['name' => 'Logo Brand', 'website' => 'https://logo.test']);
        $websiteType = BriefFormType::create([
            'name' => 'Website',
            'slug' => 'website',
            'default_form_path' => '/brief-form',
            'is_active' => true,
        ]);
        $logoType = BriefFormType::create([
            'name' => 'Logo',
            'slug' => 'logo',
            'default_form_path' => '/brief-form',
            'is_active' => true,
        ]);
        $websiteForm = $this->createForm($websiteBrand, $websiteType, 'Website Form', 'website', 'project_name');
        $logoForm = $this->createForm($logoBrand, $logoType, 'Logo Form', 'logo', 'logo_name');
        $websiteSale = $this->createSale($client, $websiteBrand, 'Website Package', '2026-07-20');
        $logoSale = $this->createSale($client, $logoBrand, 'Logo Package', '2026-07-21');

        BriefSubmission::create([
            'sale_id' => $websiteSale->id,
            'brand_brief_form_id' => $websiteForm->id,
            'brief_type' => 'website',
            'form_path' => '/brief-form',
            'data' => ['project_name' => 'Acme Portal'],
            'meta' => ['field_labels' => ['project_name' => 'Project Name']],
            'status' => BriefSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        BriefSubmission::create([
            'sale_id' => $logoSale->id,
            'brand_brief_form_id' => null,
            'brief_type' => 'logo',
            'form_path' => '/brief-form',
            'data' => ['logo_name' => 'Acme Mark'],
            'meta' => ['field_labels' => ['logo_name' => 'Logo Name']],
            'status' => BriefSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->withToken('brief-test-key')
            ->getJson("/api/v1/clients/{$client->id}/brief-forms")
            ->assertOk()
            ->assertJsonPath('summary.sales', 2)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.submitted', 2)
            ->assertJsonPath('brief_forms.0.sale.id', $logoSale->id)
            ->assertJsonPath('brief_forms.0.answers.0.label', 'Logo Name')
            ->assertJsonPath('brief_forms.1.sale.id', $websiteSale->id)
            ->assertJsonPath('brief_forms.1.answers.0.value', 'Acme Portal');
    }

    public function test_it_exposes_uploaded_files_through_the_protected_download_route(): void
    {
        Storage::fake('public');
        $client = Client::create(['name' => 'File Client', 'email' => 'file@example.com']);
        $brand = Brand::create(['name' => 'File Brand', 'website' => 'https://files.test']);
        $type = BriefFormType::create([
            'name' => 'Website',
            'slug' => 'website',
            'default_form_path' => '/brief-form',
            'is_active' => true,
        ]);
        $form = $this->createForm($brand, $type, 'Website Form', 'website', 'project_name');
        $sale = $this->createSale($client, $brand, 'File Package', '2026-07-22');
        $path = "brief-uploads/{$sale->id}/logo_file/stored.pdf";
        Storage::disk('public')->put($path, 'pdf-contents');

        $submission = BriefSubmission::create([
            'sale_id' => $sale->id,
            'brand_brief_form_id' => $form->id,
            'brief_type' => 'website',
            'form_path' => '/brief-form',
            'data' => ['project_name' => 'File Project'],
            'attachments' => [[
                'field' => 'logo_file',
                'original_name' => 'brand-logo.pdf',
                'path' => $path,
            ]],
            'status' => BriefSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->withToken('brief-test-key')
            ->getJson("/api/v1/clients/{$client->id}/brief-forms")
            ->assertOk()
            ->assertJsonPath('brief_forms.0.files.0.name', 'brand-logo.pdf')
            ->assertJsonPath('brief_forms.0.files.0.index', 0);

        $this->withToken('brief-test-key')
            ->get("/api/v1/clients/{$client->id}/brief-submissions/{$submission->id}/files/0")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_legacy_sale_endpoint_resolves_the_full_client_brief_history(): void
    {
        $client = Client::create(['name' => 'Legacy Client', 'email' => 'legacy@example.com']);
        $brand = Brand::create(['name' => 'Legacy Brand', 'website' => 'https://legacy.test']);
        $type = BriefFormType::create([
            'name' => 'Website',
            'slug' => 'website',
            'default_form_path' => '/brief-form',
            'is_active' => true,
        ]);
        $form = $this->createForm($brand, $type, 'Legacy Website Form', 'website', 'project_name');
        $sale = $this->createSale($client, $brand, 'Legacy Package', '2026-07-22');
        BriefSubmission::create([
            'sale_id' => $sale->id,
            'brand_brief_form_id' => $form->id,
            'brief_type' => 'website',
            'form_path' => '/brief-form',
            'data' => ['project_name' => 'Legacy Portal'],
            'status' => BriefSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->withToken('brief-test-key')
            ->getJson("/api/v1/sales/{$sale->id}/client-brief-forms")
            ->assertOk()
            ->assertJsonPath('client.id', $client->id)
            ->assertJsonPath('brief_forms.0.answers.0.value', 'Legacy Portal');
    }

    private function createForm(
        Brand $brand,
        BriefFormType $type,
        string $name,
        string $slug,
        string $fieldId
    ): BrandBriefForm {
        return BrandBriefForm::create([
            'brand_id' => $brand->id,
            'brief_form_type_id' => $type->id,
            'name' => $name,
            'form_path' => '/brief-form',
            'slug' => $slug,
            'schema' => [
                'version' => 1,
                'title' => $name,
                'sections' => [[
                    'id' => 'main',
                    'title' => 'Main',
                    'fields' => [[
                        'id' => $fieldId,
                        'type' => 'text',
                        'label' => ucwords(str_replace('_', ' ', $fieldId)),
                    ]],
                ]],
            ],
            'schema_version' => 1,
            'is_active' => true,
        ]);
    }

    private function createSale(Client $client, Brand $brand, string $title, string $date): Sale
    {
        return Sale::create([
            'title' => $title,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_email' => $client->email,
            'amount' => 100,
            'sale_date' => $date,
            'brand_id' => $brand->id,
            'status' => 'completed',
            'sale_type' => Sale::TYPE_FRONT,
            'is_draft' => false,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });
        Schema::create('brief_form_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('default_form_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('brand_brief_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('brief_form_type_id')->nullable();
            $table->string('name');
            $table->string('form_path');
            $table->string('slug')->nullable();
            $table->json('schema')->nullable();
            $table->unsignedTinyInteger('schema_version')->default(1);
            $table->string('document')->nullable();
            $table->string('document_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('received_amount', 15, 2)->default(0);
            $table->date('sale_date')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('status')->default('completed');
            $table->string('sale_type')->default('front');
            $table->boolean('is_refunded')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->timestamps();
        });
        Schema::create('brief_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('brand_brief_form_id')->nullable();
            $table->string('brief_type');
            $table->string('form_path');
            $table->json('data');
            $table->json('attachments')->nullable();
            $table->json('meta')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_ip')->nullable();
            $table->timestamps();
        });
    }
}
