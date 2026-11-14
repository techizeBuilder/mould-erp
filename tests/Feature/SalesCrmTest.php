<?php

namespace Tests\Feature;

use App\Models\BuyerQualification;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadItem;
use App\Models\Mould;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\DemoSalesSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesCrmTest extends TestCase
{
    use RefreshDatabase;

    protected const MODULES = ['sales_leads', 'sales_qualification', 'sales_quotations', 'sales_followups', 'sales_retention'];

    protected function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    /** A sub-admin with the given actions on each module (show is implied). */
    protected function subAdminWith(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $module => $actions) {
            $row = ['can_show' => true];
            foreach ($actions as $action) {
                $row["can_$action"] = true;
            }
            $user->permissions()->create(['module' => $module] + $row);
        }

        return $user;
    }

    protected function mould(array $extra = []): Mould
    {
        return Mould::create($extra + ['mould_type' => 'blow', 'shape' => 'round', 'capacity_ml' => 500, 'neck_size_mm' => 28, 'status' => 'active']);
    }

    protected function product(array $extra = []): Product
    {
        return Product::create($extra + [
            'bill_name' => 'Test bottle', 'owner_type' => 'company', 'product_type' => 'bottle', 'business_type' => 'manufacturing',
            'capacity_ml' => 500, 'shape' => 'round', 'material' => 'PET', 'neck_size_mm' => 28, 'weight_grams' => 18, 'colour' => 'Clear',
            'unit' => 'pcs', 'status' => 'active',
        ]);
    }

    /** A custom-spec line as the form posts it. */
    protected function customLine(array $extra = []): array
    {
        return $extra + [
            'product_type' => 'bottle', 'capacity_value' => 1, 'capacity_unit' => 'l', 'material' => 'HDPE', 'shape' => 'round',
            'neck_size_mm' => 38, 'neck_type' => 'Screw (threaded)', 'weight_grams' => 40, 'colour' => 'White',
            'closure' => 'cap', 'decoration' => 'screen_print', 'fill_product' => 'Hair oil',
            'quantity' => 12000, 'qty_basis' => 'per_month', 'target_price' => 9.5,
        ];
    }

    protected function leadPayload(array $extra = []): array
    {
        return $extra + [
            'enquiry_date' => '2026-11-01', 'company_name' => 'Sharma Oils Pvt Ltd', 'contact_person' => 'Anil Sharma', 'phone' => '9876543210',
            'industry' => 'edible_oil', 'source' => 'indiamart', 'source_detail' => 'Buy lead #123', 'status' => 'new',
            'items' => [$this->customLine()],
        ];
    }

    protected function lead(array $extra = [], array $lines = []): Lead
    {
        $lead = Lead::create($extra + [
            'enquiry_date' => '2026-11-01', 'company_name' => 'Sharma Oils', 'contact_person' => 'Anil', 'phone' => '9876543210',
            'industry' => 'edible_oil', 'source' => 'indiamart', 'status' => 'new',
        ]);
        foreach ($lines ?: [[]] as $line) {
            $lead->items()->create($line + ['product_type' => 'bottle', 'capacity_ml' => 1000, 'material' => 'HDPE', 'quantity' => 12000, 'qty_basis' => 'per_month']);
        }

        return $lead->fresh('items');
    }

    protected function quotationLine(array $extra = []): array
    {
        return $extra + [
            'product_type' => 'bottle', 'capacity_value' => 1, 'capacity_unit' => 'l', 'material' => 'HDPE',
            'closure' => 'none', 'decoration' => 'plain', 'quantity' => 10000, 'unit' => 'pcs', 'rate' => 10,
        ];
    }

    protected function quotationPayload(Lead|Customer $buyer, array $extra = []): array
    {
        $isLead = $buyer instanceof Lead;

        return $extra + [
            'buyer_kind' => $isLead ? 'lead' : 'customer',
            'lead_id' => $isLead ? $buyer->id : null,
            'customer_id' => $isLead ? null : $buyer->id,
            'quote_date' => today()->toDateString(),
            'valid_until' => today()->addDays(15)->toDateString(),
            'price_basis' => 'ex_works',
            'sample_status' => 'not_required',
            'gst_percent' => 18,
            'items' => [$this->quotationLine()],
        ];
    }

    /** A quotation saved through the real form. */
    protected function quotation(Lead|Customer|null $buyer = null, array $extra = []): Quotation
    {
        $buyer ??= $this->lead();
        $this->post('/sales/quotations', $this->quotationPayload($buyer, $extra))->assertRedirect();

        return Quotation::latest('id')->first();
    }

    protected function customer(array $extra = []): Customer
    {
        return Customer::create($extra + ['company_name' => 'Regular Buyer', 'phone' => '9000000001', 'status' => 'active']);
    }

    protected function order(Customer $customer, string $date, array $extra = []): CustomerOrder
    {
        return CustomerOrder::forceCreate($extra + [
            'customer_id' => $customer->id, 'description' => '500 ml Round PET Bottle', 'order_date' => $date,
            'quantity' => 1000, 'unit' => 'pcs', 'rate' => 2, 'amount' => 2000,
        ]);
    }

    // ------------------------------------------------------------- Sidebar & rights

    public function test_sidebar_shows_sales_and_crm_with_its_five_screens(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->get('/dashboard')->assertOk();
        $response->assertSee('Sales &amp; CRM', false)->assertSee('data-toggle="group-sales"', false);
        foreach (['Leads', 'Buyer Qualification', 'Quotations', 'Follow-ups', 'Customer Retention'] as $label) {
            $response->assertSee($label);
        }
        $this->assertNull(config('modules.sales'));
        $this->get('/modules/sales')->assertNotFound();
    }

    public function test_each_screen_is_its_own_line_in_the_permission_matrix(): void
    {
        $sub = $this->subAdminWith([]);
        $this->actingAs($this->superAdmin());

        $response = $this->get("/admin/users/{$sub->id}/permissions")->assertOk()->assertSee('Sales &amp; CRM', false);
        foreach (self::MODULES as $module) {
            $response->assertSee("permissions[$module]", false);
        }

        $this->put("/admin/users/{$sub->id}/permissions", ['permissions' => ['sales_quotations' => ['edit' => 1]]])->assertRedirect();
        $this->assertTrue($sub->fresh()->canModule('sales_quotations', 'edit'));
        $this->assertFalse($sub->fresh()->canModule('sales_leads', 'show'));
    }

    public function test_migration_carries_old_sales_rights_over_to_every_new_screen(): void
    {
        $migration = require database_path('migrations/2026_11_07_000001_create_sales_crm_tables.php');
        $user = User::factory()->create();

        $migration->down();
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'module' => 'sales', 'can_show' => 1, 'can_view' => 1, 'can_create' => 1, 'can_edit' => 0, 'can_delete' => 0]);
        $migration->up();

        $this->assertDatabaseMissing('user_permissions', ['user_id' => $user->id, 'module' => 'sales']);
        foreach (self::MODULES as $module) {
            $this->assertDatabaseHas('user_permissions', ['user_id' => $user->id, 'module' => $module, 'can_create' => 1, 'can_edit' => 0]);
        }
    }

    public function test_users_without_rights_are_refused_everywhere(): void
    {
        $lead = $this->lead();
        $customer = $this->customer();
        $followUp = FollowUp::create(['lead_id' => $lead->id, 'type' => 'call', 'purpose' => 'general', 'due_at' => now()]);
        $quotation = Quotation::create(['lead_id' => $lead->id, 'quote_date' => today(), 'valid_until' => today()]);

        $this->actingAs($this->subAdminWith([]));

        foreach ([
            '/sales/leads', '/sales/leads/create', "/sales/leads/{$lead->id}", "/sales/leads/{$lead->id}/edit",
            '/sales/qualification', "/sales/qualification/{$lead->id}",
            '/sales/quotations', '/sales/quotations/create', "/sales/quotations/{$quotation->id}", "/sales/quotations/{$quotation->id}/edit", "/sales/quotations/{$quotation->id}/print",
            '/sales/followups', '/sales/followups/create', "/sales/followups/{$followUp->id}", "/sales/followups/{$followUp->id}/edit",
            '/sales/retention', '/sales/retention/create', "/sales/retention/{$customer->id}", "/sales/retention/{$customer->id}/edit",
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post('/sales/leads', [])->assertForbidden();
        $this->delete("/sales/leads/{$lead->id}")->assertForbidden();
        $this->put("/sales/qualification/{$lead->id}", [])->assertForbidden();
        $this->post('/sales/quotations', [])->assertForbidden();
        $this->post("/sales/quotations/{$quotation->id}/status", ['status' => 'sent'])->assertForbidden();
        $this->post("/sales/quotations/{$quotation->id}/revise")->assertForbidden();
        $this->post("/sales/followups/{$followUp->id}/complete", ['outcome' => 'x'])->assertForbidden();
        $this->post('/sales/retention-reminders')->assertForbidden();
        $this->post("/sales/retention/{$customer->id}/orders", [])->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/sales/leads')->assertRedirect('/login');
    }

    public function test_show_only_rights_hide_records(): void
    {
        $this->lead(['company_name' => 'Secret Buyer']);
        $this->actingAs($this->subAdminWith(array_fill_keys(self::MODULES, [])));

        $this->get('/sales/leads')->assertOk()->assertDontSee('Secret Buyer')->assertDontSee('Add Lead')
            ->assertSee("You don't have permission to view these records", false);
        $this->get('/sales/qualification')->assertOk()->assertDontSee('Secret Buyer');
        $this->get('/sales/quotations')->assertOk()->assertDontSee('New Quotation');
        $this->get('/sales/followups')->assertOk()->assertDontSee('Schedule Follow-up');
        $this->get('/sales/retention')->assertOk()->assertDontSee('Add Customer');
    }

    public function test_view_only_rights_show_records_without_action_buttons(): void
    {
        $lead = $this->lead(['company_name' => 'Visible Buyer']);
        $this->actingAs($this->subAdminWith(['sales_leads' => ['view']]));

        $this->get('/sales/leads')->assertOk()->assertSee('Visible Buyer')->assertDontSee('Add Lead');
        $this->get("/sales/leads/{$lead->id}")->assertOk()->assertDontSee('Create quotation')->assertDontSee('Qualify buyer');
        $this->get("/sales/leads/{$lead->id}/edit")->assertForbidden();
        $this->delete("/sales/leads/{$lead->id}")->assertForbidden();
    }

    // ------------------------------------------------------------- Leads

    public function test_lead_is_created_with_source_and_custom_product_spec(): void
    {
        $this->actingAs($admin = $this->superAdmin());

        $this->post('/sales/leads', $this->leadPayload())->assertRedirect();

        $lead = Lead::with('items')->sole();
        $this->assertSame('LD-0001', $lead->code);
        $this->assertSame('indiamart', $lead->source);
        $this->assertSame('IndiaMART', $lead->sourceLabel());
        $this->assertSame('Buy lead #123', $lead->source_detail);
        $this->assertSame($admin->id, $lead->created_by);

        $item = $lead->items->sole();
        $this->assertNull($item->product_id);
        $this->assertSame('1000.00', $item->capacity_ml);
        $this->assertStringContainsString('1 L Round HDPE Bottle', $item->description);
        $this->assertStringContainsString('38 mm Screw (threaded)', $item->description);
        $this->assertStringContainsString('40 g', $item->description);
        $this->assertSame('With cap · Screen printing · For Hair oil', $item->requirementNote());

        $this->get("/sales/leads/{$lead->id}")->assertOk()->assertSee('IndiaMART')->assertSee('Buy lead #123')->assertSee('1 L Round HDPE Bottle');
    }

    public function test_lead_source_is_mandatory_and_must_be_from_the_list(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/sales/leads', $this->leadPayload(['source' => '']))->assertSessionHasErrors('source');
        $this->post('/sales/leads', $this->leadPayload(['source' => 'carrier_pigeon']))->assertSessionHasErrors('source');
        $this->assertSame(0, Lead::count());
    }

    public function test_every_listed_source_is_accepted_and_filterable(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (array_keys(Lead::SOURCES) as $i => $source) {
            $this->post('/sales/leads', $this->leadPayload(['source' => $source, 'company_name' => "Buyer $source", 'phone' => '98765432'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]))
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(count(Lead::SOURCES), Lead::count());

        $this->get('/sales/leads?source=exhibition')->assertOk()->assertSee('Buyer exhibition')->assertDontSee('Buyer referral');
    }

    public function test_lead_needs_at_least_one_product_and_a_complete_custom_spec(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/sales/leads', $this->leadPayload(['items' => []]))->assertSessionHasErrors('items');
        $this->post('/sales/leads', $this->leadPayload(['items' => [['closure' => 'none', 'decoration' => 'plain', 'quantity' => 100, 'qty_basis' => 'per_month']]]))
            ->assertSessionHasErrors(['items.0.product_type', 'items.0.capacity_value', 'items.0.material']);
        $this->post('/sales/leads', $this->leadPayload(['items' => [$this->customLine(['quantity' => 0])]]))->assertSessionHasErrors('items.0.quantity');
        $this->post('/sales/leads', $this->leadPayload(['items' => [$this->customLine(['qty_basis' => 'sometimes'])]]))->assertSessionHasErrors('items.0.qty_basis');
        $this->assertSame(0, Lead::count());
    }

    public function test_validation_error_keeps_the_form_filled_and_shows_the_line_error(): void
    {
        $this->actingAs($this->superAdmin());

        $this->from('/sales/leads/create')->followingRedirects()
            ->post('/sales/leads', $this->leadPayload(['items' => [$this->customLine(['quantity' => 0, 'fill_product' => 'Mustard oil'])]]))
            ->assertOk()->assertSee('Mustard oil')->assertSee('The quantity field must be at least 1.');
    }

    public function test_phone_gstin_and_date_are_validated(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/sales/leads', $this->leadPayload(['phone' => 'call me']))->assertSessionHasErrors('phone');
        $this->post('/sales/leads', $this->leadPayload(['gstin' => 'BADGST']))->assertSessionHasErrors('gstin');
        $this->post('/sales/leads', $this->leadPayload(['enquiry_date' => today()->addDay()->toDateString()]))->assertSessionHasErrors('enquiry_date');

        $this->post('/sales/leads', $this->leadPayload(['gstin' => '27abcde1234f1z5']))->assertSessionHasNoErrors();
        $this->assertSame('27ABCDE1234F1Z5', Lead::sole()->gstin);
    }

    public function test_catalogue_line_takes_specs_from_the_product_and_ignores_posted_ones(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product(['mould_id' => $this->mould()->id]);

        $this->post('/sales/leads', $this->leadPayload(['items' => [[
            'product_id' => $product->id, 'capacity_value' => 999, 'capacity_unit' => 'l', 'material' => 'PP',
            'closure' => 'cap', 'decoration' => 'plain', 'quantity' => 5000, 'qty_basis' => 'per_month',
        ]]]))->assertSessionHasNoErrors();

        $item = LeadItem::sole();
        $this->assertSame('500.00', $item->capacity_ml);
        $this->assertSame('PET', $item->material);
        $this->assertSame($product->name, $item->description);
        $this->assertSame('existing', $item->mould_status);
        $this->assertSame($product->mould_id, $item->mould_id);
        $this->assertSame('cap', $item->closure);
    }

    public function test_mould_status_for_catalogue_products(): void
    {
        $withMould = $this->product(['mould_id' => $this->mould()->id]);
        $noMould = $this->product(['capacity_ml' => 750]);
        $traded = $this->product(['business_type' => 'trading', 'capacity_ml' => 250]);

        $status = fn (Product $p) => $this->lead([], [['product_id' => $p->id]])->items->first()->mould_status;

        $this->assertSame('existing', $status($withMould));
        $this->assertSame('new', $status($noMould));
        $this->assertSame('trading', $status($traded));
    }

    public function test_custom_spec_is_matched_against_mould_master(): void
    {
        $mould = $this->mould(['capacity_ml' => 1000, 'neck_size_mm' => 38, 'shape' => 'round']);
        $line = ['product_type' => 'bottle', 'capacity_ml' => 1000, 'material' => 'HDPE'];
        $status = fn (array $extra) => $this->lead([], [$extra + $line])->items->first();

        $exact = $status(['neck_size_mm' => 38, 'shape' => 'round']);
        $this->assertSame('existing', $exact->mould_status);
        $this->assertSame($mould->id, $exact->mould_id);

        $this->assertSame('existing', $status([])->mould_status, 'Neck and shape are not required to match when the buyer gave none');
        $this->assertSame('new', $status(['neck_size_mm' => 28])->mould_status, 'Different neck');
        $this->assertSame('new', $status(['shape' => 'square'])->mould_status, 'Different shape');
        $this->assertSame('new', $status(['capacity_ml' => 2000])->mould_status, 'Different volume');
        $this->assertSame('new', $status(['product_type' => 'preform'])->mould_status, 'A blow mould cannot make a preform');

        $mould->update(['status' => 'inactive']);
        $this->assertSame('new', $status([])->mould_status, 'Inactive moulds do not count');
    }

    public function test_spec_snapshot_survives_changes_to_the_masters(): void
    {
        $product = $this->product();
        $item = $this->lead([], [['product_id' => $product->id]])->items->first();
        $description = $item->description;

        $product->delete();

        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertSame($description, $item->description);
    }

    public function test_monthly_quantity_converts_each_basis(): void
    {
        $lead = $this->lead([], [
            ['quantity' => 10000, 'qty_basis' => 'per_month'],
            ['quantity' => 30000, 'qty_basis' => 'per_quarter'],
            ['quantity' => 120000, 'qty_basis' => 'per_year'],
            ['quantity' => 24000, 'qty_basis' => 'one_time'],
        ]);

        $this->assertSame([10000, 10000, 10000, 2000], $lead->items->map->monthlyQuantity()->all());
        $this->assertSame(32000, $lead->estimatedMonthlyVolume());
    }

    public function test_lead_update_replaces_lines_and_lost_needs_a_reason(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead([], [[], []]);

        $this->put("/sales/leads/{$lead->id}", $this->leadPayload(['status' => 'lost']))->assertSessionHasErrors('lost_reason');
        $this->put("/sales/leads/{$lead->id}", $this->leadPayload(['status' => 'won']))->assertSessionHasErrors('status');

        $this->put("/sales/leads/{$lead->id}", $this->leadPayload(['status' => 'lost', 'lost_reason' => 'moq', 'company_name' => 'Renamed']))->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertSame('Renamed', $lead->company_name);
        $this->assertSame('lost', $lead->status);
        $this->assertSame('Quantity below our MOQ', $lead->lostReasonLabel());
        $this->assertCount(1, $lead->items);
    }

    public function test_duplicate_mobile_is_flagged_on_create(): void
    {
        $this->actingAs($this->superAdmin());
        $first = $this->lead(['phone' => '9876543210']);

        $this->post('/sales/leads', $this->leadPayload(['phone' => '9876543210']))->assertSessionHas('success', fn ($m) => str_contains($m, $first->code));
    }

    public function test_lead_filters_by_product_type_and_product(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product();
        $this->lead(['company_name' => 'Jar Buyer'], [['product_type' => 'jar']]);
        $this->lead(['company_name' => 'Catalogue Buyer'], [['product_id' => $product->id]]);

        $this->get('/sales/leads?product_type=jar')->assertSee('Jar Buyer')->assertDontSee('Catalogue Buyer');
        $this->get("/sales/leads?product_id={$product->id}")->assertSee('Catalogue Buyer')->assertDontSee('Jar Buyer');
    }

    public function test_lead_with_a_quotation_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();
        $this->quotation($lead);

        $this->delete("/sales/leads/{$lead->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);

        $free = $this->lead();
        $this->delete("/sales/leads/{$free->id}")->assertRedirect('/sales/leads');
        $this->assertDatabaseMissing('leads', ['id' => $free->id]);
    }

    // ------------------------------------------------------------- Buyer qualification

    protected function qualificationPayload(array $extra = []): array
    {
        return $extra + [
            'buyer_type' => 'brand_owner', 'monthly_volume' => 120000, 'price_fit' => 'negotiable', 'mould_fit' => 'existing',
            'decision_authority' => 'decision_maker', 'purchase_timeline' => 'immediate', 'payment_terms' => 'part_advance',
            'gst_verified' => 1, 'sample_required' => 1, 'compliance_required' => 'Food grade', 'decision' => 'qualified',
        ];
    }

    public function test_qualification_form_is_prefilled_from_the_product_requirement(): void
    {
        $this->actingAs($this->superAdmin());
        $this->mould(['capacity_ml' => 1000, 'neck_size_mm' => null, 'shape' => null]);
        $lead = $this->lead();

        $this->assertSame('existing', $lead->suggestedMouldFit());
        $this->get("/sales/qualification/{$lead->id}")->assertOk()->assertSee('value="12000"', false)->assertSee('Existing mould available');
    }

    public function test_suggested_mould_fit_is_new_when_any_line_needs_a_new_mould(): void
    {
        $this->mould(['capacity_ml' => 1000, 'neck_size_mm' => null, 'shape' => null]);
        $lead = $this->lead([], [[], ['capacity_ml' => 3000]]);

        $this->assertSame('new_buyer_funded', $lead->suggestedMouldFit());
    }

    public function test_score_is_computed_on_the_server_and_lead_status_follows_the_decision(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $lead = $this->lead();

        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload(['score' => 1, 'grade' => 'D']))->assertRedirect("/sales/leads/{$lead->id}");

        $q = BuyerQualification::sole();
        // 15 + 25 + 10 + 20 + 5 + 10 + 9
        $this->assertSame(94, $q->score);
        $this->assertSame('A', $q->grade);
        $this->assertSame('qualified', $q->recommendation());
        $this->assertTrue($q->gst_verified);
        $this->assertSame($admin->id, $q->qualified_by);
        $this->assertSame('qualified', $lead->fresh()->status);
    }

    public function test_reassessing_updates_the_same_record(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();

        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload());
        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload(['decision' => 'nurture', 'purchase_timeline' => 'exploring', 'gst_verified' => 0]));

        $q = BuyerQualification::sole();
        $this->assertSame(85, $q->score);
        $this->assertFalse($q->gst_verified);
        $this->assertSame('nurture', $lead->fresh()->status);
    }

    public function test_qualification_rules(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();

        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload(['buyer_type' => 'alien']))->assertSessionHasErrors('buyer_type');
        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload(['decision' => 'disqualified']))->assertSessionHasErrors('decision_reason');
        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload(['mould_fit' => 'not_feasible']))->assertSessionHasErrors('decision');
        $this->assertSame(0, BuyerQualification::count());

        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload(['decision' => 'disqualified', 'decision_reason' => 'Too small']))->assertSessionHasNoErrors();
        $this->assertSame('disqualified', $lead->fresh()->status);
    }

    public function test_disqualified_lead_cannot_be_quoted_and_open_quote_blocks_disqualifying(): void
    {
        $this->actingAs($this->superAdmin());

        $bad = $this->lead(['status' => 'disqualified']);
        $this->get("/sales/quotations/create?lead={$bad->id}")->assertRedirect("/sales/leads/{$bad->id}");
        $this->post('/sales/quotations', $this->quotationPayload($bad))->assertSessionHasErrors('lead_id');
        $this->assertSame(0, Quotation::count());

        $quoted = $this->lead();
        $this->quotation($quoted);
        $this->put("/sales/qualification/{$quoted->id}", $this->qualificationPayload(['decision' => 'disqualified', 'decision_reason' => 'x']))->assertSessionHasErrors('decision');
    }

    public function test_qualifying_a_quoted_lead_does_not_pull_it_back_a_stage(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();
        $this->quotation($lead);

        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload())->assertSessionHasNoErrors();

        $this->assertSame('quoted', $lead->fresh()->status);
    }

    public function test_saving_a_qualification_checks_create_then_edit_rights(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->subAdminWith(['sales_qualification' => ['view']]));
        $this->get("/sales/qualification/{$lead->id}")->assertOk()->assertDontSee('Save qualification');
        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload())->assertForbidden();

        $this->actingAs($this->subAdminWith(['sales_qualification' => ['view', 'create']]));
        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload())->assertRedirect();
        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload())->assertForbidden();
    }

    public function test_qualification_list_filters_and_removal(): void
    {
        $this->actingAs($this->superAdmin());
        $done = $this->lead(['company_name' => 'Assessed Co']);
        $this->lead(['company_name' => 'Waiting Co']);
        $this->put("/sales/qualification/{$done->id}", $this->qualificationPayload());
        $this->get('/dashboard'); // clears the flash message naming the buyer

        $this->get('/sales/qualification?decision=pending')->assertSee('Waiting Co')->assertDontSee('Assessed Co');
        $this->get('/sales/qualification?decision=qualified')->assertSee('Assessed Co')->assertDontSee('Waiting Co');
        $this->get('/sales/qualification?grade=A')->assertSee('Assessed Co')->assertDontSee('Waiting Co');

        $this->delete("/sales/qualification/{$done->id}")->assertRedirect();
        $this->assertSame(0, BuyerQualification::count());
        $this->assertSame('contacted', $done->fresh()->status);
    }

    // ------------------------------------------------------------- Quotations

    public function test_quotation_starts_from_what_the_lead_asked_for(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead([], [['fill_product' => 'Coconut oil', 'target_price' => 9.5]]);
        $lead->qualification()->forceCreate(['lead_id' => $lead->id, 'buyer_type' => 'trader', 'monthly_volume' => 1, 'price_fit' => 'unknown', 'mould_fit' => 'existing',
            'decision_authority' => 'unknown', 'purchase_timeline' => 'exploring', 'payment_terms' => 'credit_30', 'sample_required' => true]);

        $this->get("/sales/quotations/create?lead={$lead->id}")->assertOk()
            ->assertSee('Coconut oil')->assertSee('value="9.5"', false)
            ->assertSee('<option value="pending" selected', false)     // sample carried from qualification
            ->assertSee('<option value="credit_30" selected', false);  // payment terms too
    }

    public function test_quotation_totals_with_mould_charge_discount_and_freight(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $lead = $this->lead();

        $this->post('/sales/quotations', $this->quotationPayload($lead, [
            'discount_percent' => 10, 'freight_amount' => 1000,
            'items' => [
                $this->quotationLine(['quantity' => 10000, 'rate' => 2, 'mould_charge' => 50000, 'moq' => 5000]),
                $this->quotationLine(['quantity' => 5000, 'rate' => 4]),
            ],
        ]))->assertSessionHasNoErrors();

        $q = Quotation::with('items')->sole();
        $this->assertSame('QTN-0001', $q->number());
        $this->assertSame('draft', $q->status);
        $this->assertSame('20000.00', $q->items[0]->amount);
        $this->assertSame('40000.00', $q->subtotal);
        $this->assertSame('4000.00', $q->discount_amount);
        $this->assertSame('50000.00', $q->mould_total);
        $this->assertSame('15660.00', $q->tax_amount);
        $this->assertSame('102660.00', $q->total);
        $this->assertSame($admin->id, $q->created_by);
        $this->assertSame('quoted', $lead->fresh()->status);
    }

    public function test_quotation_validation(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();

        $this->post('/sales/quotations', $this->quotationPayload($lead, ['lead_id' => null]))->assertSessionHasErrors('lead_id');
        $this->post('/sales/quotations', $this->quotationPayload($lead, ['valid_until' => today()->subDay()->toDateString()]))->assertSessionHasErrors('valid_until');
        $this->post('/sales/quotations', $this->quotationPayload($lead, ['items' => []]))->assertSessionHasErrors('items');
        $this->post('/sales/quotations', $this->quotationPayload($lead, ['items' => [$this->quotationLine(['rate' => null])]]))->assertSessionHasErrors('items.0.rate');
        $this->post('/sales/quotations', $this->quotationPayload($lead, ['items' => [$this->quotationLine(['unit' => 'kg'])]]))->assertSessionHasErrors('items.0.unit');
        $this->post('/sales/quotations', $this->quotationPayload($lead, ['discount_percent' => 101]))->assertSessionHasErrors('discount_percent');
        $this->assertSame(0, Quotation::count());
    }

    public function test_status_moves_are_restricted_and_logged(): void
    {
        $this->actingAs($this->superAdmin());
        $q = $this->quotation();

        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'won', 'won_on' => today()->toDateString()])->assertSessionHas('error');
        $this->assertSame('draft', $q->fresh()->status);

        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent'])->assertSessionHasNoErrors();
        $q->refresh();
        $this->assertSame('sent', $q->status);
        $this->assertTrue($q->sent_on->isToday());

        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'negotiation', 'note' => 'Wants 5% off']);
        $this->assertSame('negotiation', $q->fresh()->status);
        $this->assertDatabaseHas('quotation_status_logs', ['quotation_id' => $q->id, 'from_status' => 'sent', 'to_status' => 'negotiation', 'note' => 'Wants 5% off']);

        $this->get("/sales/quotations/{$q->id}")->assertOk()->assertSee('Wants 5% off');
    }

    public function test_every_illegal_transition_throws(): void
    {
        $q = Quotation::create(['quote_date' => today(), 'valid_until' => today()]);

        foreach (['won', 'lost', 'revised'] as $closed) {
            $q->status = $closed;
            foreach (array_keys(Quotation::STATUSES) as $to) {
                try {
                    $q->changeStatus($to, [], null);
                    $this->fail("$closed → $to should have been refused");
                } catch (DomainException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function test_winning_turns_the_lead_into_a_customer_with_order_history(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $product = $this->product(['mould_id' => $this->mould()->id]);
        $lead = $this->lead(['company_name' => 'New Buyer Ltd', 'phone' => '9123456780', 'city' => 'Pune', 'source' => 'exhibition', 'assigned_to' => $admin->id]);
        $lead->qualification()->forceCreate(['lead_id' => $lead->id, 'buyer_type' => 'distributor', 'monthly_volume' => 1, 'price_fit' => 'unknown', 'mould_fit' => 'existing',
            'decision_authority' => 'unknown', 'purchase_timeline' => 'exploring', 'payment_terms' => 'credit_30']);
        $followUp = FollowUp::create(['lead_id' => $lead->id, 'type' => 'call', 'purpose' => 'general', 'due_at' => now()]);

        $q = $this->quotation($lead, ['discount_percent' => 10, 'items' => [
            $this->quotationLine(['product_id' => $product->id, 'quantity' => 1000, 'rate' => 10, 'mould_charge' => 500]),
            $this->quotationLine(['quantity' => 200, 'rate' => 5]),
        ]]);
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'won', 'po_number' => 'PO-77', 'won_on' => '2026-11-05'])->assertSessionHasNoErrors();

        $customer = Customer::sole();
        $this->assertSame('CUS-0001', $customer->code);
        $this->assertSame('New Buyer Ltd', $customer->company_name);
        $this->assertSame('Pune', $customer->city);
        $this->assertSame('exhibition', $customer->source);
        $this->assertSame('distributor', $customer->buyer_type);
        $this->assertSame('credit_30', $customer->payment_terms);
        $this->assertSame($admin->id, $customer->assigned_to);

        $this->assertSame('won', $lead->fresh()->status);
        $this->assertSame($customer->id, $lead->fresh()->customer_id);
        $this->assertSame($customer->id, $q->fresh()->customer_id);
        $this->assertSame($customer->id, $followUp->fresh()->customer_id);

        $orders = CustomerOrder::orderBy('id')->get();
        $this->assertCount(2, $orders);
        $this->assertSame($product->id, $orders[0]->product_id);
        $this->assertSame('PO-77', $orders[0]->po_number);
        $this->assertSame('2026-11-05', $orders[0]->order_date->toDateString());
        // Net of the 10% discount; the one-time mould charge is not repeat business.
        $this->assertSame('9.000', $orders[0]->rate);
        $this->assertSame('9000.00', $orders[0]->amount);
        $this->assertSame('900.00', $orders[1]->amount);
        $this->assertSame(9900.0, $customer->fresh('orders')->lifetimeValue());
    }

    public function test_a_won_repeat_enquiry_links_to_the_existing_customer(): void
    {
        $this->actingAs($this->superAdmin());
        $byPhone = $this->customer(['phone' => '9111111111']);
        $byGst = $this->customer(['phone' => '9222222222', 'gstin' => '27ABCDE1234F1Z5']);

        foreach ([
            [['phone' => '9111111111'], $byPhone],
            [['phone' => '9333333333', 'gstin' => '27ABCDE1234F1Z5'], $byGst],
        ] as [$leadFields, $expected]) {
            $q = $this->quotation($this->lead($leadFields));
            $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);
            $this->post("/sales/quotations/{$q->id}/status", ['status' => 'won', 'won_on' => today()->toDateString()]);

            $this->assertSame($expected->id, $q->fresh()->customer_id);
        }

        $this->assertSame(2, Customer::count());
    }

    public function test_quoting_an_existing_customer_directly_and_winning_adds_to_their_orders(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $q = $this->quotation($customer);

        $this->assertNull($q->lead_id);
        $this->assertSame('Regular Buyer', $q->buyerName());

        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'won', 'won_on' => today()->toDateString()]);

        $this->assertSame(1, Customer::count());
        $this->assertSame(1, $customer->orders()->count());
    }

    public function test_winning_requires_an_order_date_and_losing_requires_a_reason(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();
        $q = $this->quotation($lead);
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);

        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'won'])->assertSessionHasErrors('won_on');
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'won', 'won_on' => today()->addDay()->toDateString()])->assertSessionHasErrors('won_on');
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'lost'])->assertSessionHasErrors('lost_reason');
        $this->assertSame('sent', $q->fresh()->status);
        $this->assertSame(0, Customer::count());

        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'lost', 'lost_reason' => 'price', 'competitor' => 'ABC Plastics'])->assertSessionHasNoErrors();

        $q->refresh();
        $this->assertSame('lost', $q->status);
        $this->assertSame('ABC Plastics', $q->competitor);
        $this->assertSame('lost', $lead->fresh()->status);
        $this->assertSame('price', $lead->fresh()->lost_reason);
        $this->assertSame(0, CustomerOrder::count());
    }

    public function test_lead_stays_quoted_while_another_quotation_is_open(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();
        $first = $this->quotation($lead);
        $this->quotation($lead);

        $this->post("/sales/quotations/{$first->id}/status", ['status' => 'lost', 'lost_reason' => 'price']);

        $this->assertSame('quoted', $lead->fresh()->status);
    }

    public function test_only_a_draft_can_be_edited(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();
        $q = $this->quotation($lead);

        $this->get("/sales/quotations/{$q->id}/edit")->assertOk();
        $this->put("/sales/quotations/{$q->id}", $this->quotationPayload($lead, ['items' => [$this->quotationLine(['rate' => 20])]]))->assertSessionHasNoErrors();
        $this->assertSame('236000.00', $q->fresh()->total);

        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);

        $this->get("/sales/quotations/{$q->id}/edit")->assertRedirect("/sales/quotations/{$q->id}");
        $this->put("/sales/quotations/{$q->id}", $this->quotationPayload($lead, ['items' => [$this->quotationLine(['rate' => 1])]]))->assertSessionHas('error');
        $this->assertSame('236000.00', $q->fresh()->total);
    }

    public function test_revision_copies_the_offer_and_replaces_the_old_version(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();
        $q = $this->quotation($lead, ['terms' => 'Keep me']);

        $this->post("/sales/quotations/{$q->id}/revise")->assertSessionHas('error');   // draft: just edit it

        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);
        $this->post("/sales/quotations/{$q->id}/revise")->assertRedirect();

        $next = Quotation::latest('id')->first();
        $this->assertSame($q->code.'-R1', $next->number());
        $this->assertSame('draft', $next->status);
        $this->assertSame($q->id, $next->parent_id);
        $this->assertSame('Keep me', $next->terms);
        $this->assertNull($next->sent_on);
        $this->assertCount(1, $next->items);
        $this->assertSame($q->fresh()->total, $next->total);
        $this->assertSame('revised', $q->fresh()->status);
        $this->assertSame('quoted', $lead->fresh()->status);

        // The old version leaves the working list but stays on record.
        $this->get('/sales/quotations')->assertSee($next->number())->assertDontSee('>'.$q->number().'<', false);
        $this->get('/sales/quotations?status=revised')->assertSee($q->number());
        $this->get("/sales/quotations/{$next->id}")->assertOk()->assertSee('All versions');

        // The next new quotation number isn't thrown off by the revision.
        $this->assertSame('QTN-0002', Quotation::nextCode());

        // And a second round.
        $this->put("/sales/quotations/{$next->id}", $this->quotationPayload($lead));
        $this->post("/sales/quotations/{$next->id}/status", ['status' => 'sent']);
        $this->post("/sales/quotations/{$next->id}/revise");
        $this->assertSame($q->code.'-R2', Quotation::latest('id')->first()->number());
    }

    public function test_a_lost_quotation_can_be_revived_by_a_revision(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();
        $q = $this->quotation($lead);
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'lost', 'lost_reason' => 'price']);
        $this->assertSame('lost', $lead->fresh()->status);

        $this->post("/sales/quotations/{$q->id}/revise")->assertRedirect();

        $this->assertSame('quoted', $lead->fresh()->status);
        $this->assertNull($lead->fresh()->lost_reason);
        $this->assertNull(Quotation::latest('id')->first()->lost_reason);
    }

    public function test_won_quotation_cannot_be_revised_or_deleted(): void
    {
        $this->actingAs($this->superAdmin());
        $q = $this->quotation();
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'won', 'won_on' => today()->toDateString()]);

        $this->post("/sales/quotations/{$q->id}/revise")->assertSessionHas('error');
        $this->delete("/sales/quotations/{$q->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('quotations', ['id' => $q->id, 'status' => 'won']);
    }

    public function test_deleting_a_draft_puts_the_lead_back(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();
        $q = $this->quotation($lead);
        $this->assertSame('quoted', $lead->fresh()->status);

        $this->delete("/sales/quotations/{$q->id}")->assertRedirect('/sales/quotations');

        $this->assertDatabaseMissing('quotations', ['id' => $q->id]);
        $this->assertSame('contacted', $lead->fresh()->status);
    }

    public function test_expiry_tracking(): void
    {
        $this->actingAs($this->superAdmin());
        Carbon::setTestNow('2026-11-07 12:00');
        $lead = $this->lead();

        $expired = $this->quotation($lead, ['quote_date' => '2026-10-01', 'valid_until' => '2026-11-06']);
        $today = $this->quotation($lead, ['quote_date' => '2026-11-01', 'valid_until' => '2026-11-07']);
        $soon = $this->quotation($lead, ['quote_date' => '2026-11-01', 'valid_until' => '2026-11-14']);
        $later = $this->quotation($lead, ['quote_date' => '2026-11-01', 'valid_until' => '2026-11-30']);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($today->isExpired());
        $this->assertSame('Expired 1 d ago', $expired->validityLabel());
        $this->assertSame('Expires today', $today->validityLabel());
        $this->assertSame('23 d left', $later->validityLabel());
        $this->assertSame([$expired->id], Quotation::expired()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$today->id, $soon->id], Quotation::expiringSoon()->pluck('id')->all());

        $this->get('/dashboard'); // clears the flash message naming the last quotation
        $this->get('/sales/quotations?status=expired')->assertOk()->assertSee($expired->number())->assertDontSee($later->number());

        // A closed quotation is no longer "expired".
        $this->post("/sales/quotations/{$expired->id}/status", ['status' => 'lost', 'lost_reason' => 'price']);
        $this->assertFalse($expired->fresh()->isExpired());

        Carbon::setTestNow();
    }

    public function test_quotation_stats_and_filters(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product();
        $open = $this->quotation($this->lead(['company_name' => 'Open Co']), ['sample_status' => 'sent', 'items' => [$this->quotationLine(['product_id' => $product->id])]]);
        $won = $this->quotation($this->lead(['company_name' => 'Won Co', 'phone' => '9000000002']));
        $this->post("/sales/quotations/{$won->id}/status", ['status' => 'sent']);
        $this->post("/sales/quotations/{$won->id}/status", ['status' => 'won', 'won_on' => today()->toDateString()]);
        $lost = $this->quotation($this->lead(['company_name' => 'Lost Co', 'phone' => '9000000003']));
        $this->post("/sales/quotations/{$lost->id}/status", ['status' => 'lost', 'lost_reason' => 'price']);

        $view = $this->get('/sales/quotations')->assertOk()->viewData('stats');
        $this->assertSame(1, $view['open_count']);
        $this->assertSame(50, $view['win_rate']);
        $this->assertSame((float) $won->fresh()->total, $view['won_value']);

        $this->get('/sales/quotations?status=won')->assertSee('Won Co')->assertDontSee('Open Co');
        $this->get('/sales/quotations?sample=sent')->assertSee('Open Co')->assertDontSee('Won Co');
        $this->get("/sales/quotations?product_id={$product->id}")->assertSee('Open Co')->assertDontSee('Lost Co');
        $this->get('/sales/quotations?search=Lost')->assertSee('Lost Co')->assertDontSee('Open Co');
    }

    public function test_sample_tracking(): void
    {
        $this->actingAs($this->superAdmin());
        $q = $this->quotation();

        $this->post("/sales/quotations/{$q->id}/sample", ['sample_status' => 'bogus'])->assertSessionHasErrors('sample_status');
        $this->post("/sales/quotations/{$q->id}/sample", ['sample_status' => 'sent'])->assertSessionHasNoErrors();
        $q->refresh();
        $this->assertSame('sent', $q->sample_status);
        $this->assertTrue($q->sample_sent_on->isToday());

        $this->post("/sales/quotations/{$q->id}/sample", ['sample_status' => 'approved']);
        $this->assertSame('approved', $q->fresh()->sample_status);
        $this->assertDatabaseHas('quotation_status_logs', ['quotation_id' => $q->id, 'note' => 'Sample: Approved by buyer']);
    }

    public function test_print_page_is_for_the_buyer_and_keeps_internal_notes_off_it(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead(['company_name' => 'Print Buyer']);
        $q = $this->quotation($lead, ['terms' => 'Ex stock only', 'remarks' => 'Internal: squeeze them', 'packing' => 'Box of 100',
            'items' => [$this->quotationLine(['moq' => 5000, 'mould_charge' => 1000])]]);

        $this->get("/sales/quotations/{$q->id}/print")->assertOk()
            ->assertSee('Print Buyer')->assertSee('1 L HDPE Bottle')->assertSee('Ex stock only')->assertSee('Box of 100')
            ->assertSee('MOQ: 5,000')->assertSee('Mould charges')->assertSee('Rupees')
            ->assertDontSee('squeeze them');
    }

    // ------------------------------------------------------------- Follow-ups

    protected function followUpPayload(array $extra = []): array
    {
        return $extra + ['type' => 'call', 'purpose' => 'sample_feedback', 'due_at' => now()->addDay()->format('Y-m-d\TH:i'), 'agenda' => 'Ask about the sample'];
    }

    public function test_follow_up_can_be_scheduled_on_a_lead_quotation_or_customer(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product();
        $lead = $this->lead(['company_name' => 'Lead Co']);
        $q = $this->quotation($lead);
        $customer = $this->customer();

        $this->get("/sales/followups/create?lead={$lead->id}")->assertOk();
        $this->get("/sales/followups/create?quotation={$q->id}")->assertOk();
        $this->get("/sales/followups/create?customer={$customer->id}")->assertOk();

        $this->post('/sales/followups', $this->followUpPayload(['related_type' => 'lead', 'related_id' => $lead->id, 'product_id' => $product->id]))->assertSessionHasNoErrors();
        $this->post('/sales/followups', $this->followUpPayload(['related_type' => 'quotation', 'related_id' => $q->id]))->assertSessionHasNoErrors();
        $this->post('/sales/followups', $this->followUpPayload(['related_type' => 'customer', 'related_id' => $customer->id]))->assertSessionHasNoErrors();

        [$onLead, $onQuote, $onCustomer] = FollowUp::orderBy('id')->get();
        $this->assertSame($lead->id, $onLead->lead_id);
        $this->assertSame($product->id, $onLead->product_id);
        // A quotation's follow-up also shows on the lead's page.
        $this->assertSame($q->id, $onQuote->quotation_id);
        $this->assertSame($lead->id, $onQuote->lead_id);
        $this->assertSame('Lead Co', $onQuote->buyerName());
        $this->assertSame($q->number(), $onQuote->subject()[0]);
        $this->assertSame($customer->id, $onCustomer->customer_id);
        $this->assertSame('Regular Buyer', $onCustomer->buyerName());

        $this->get("/sales/leads/{$lead->id}")->assertSee('Sample feedback');
        $this->get("/sales/quotations/{$q->id}")->assertSee('Sample feedback');
        $this->get("/sales/retention/{$customer->id}")->assertSee('Sample feedback');
    }

    public function test_follow_up_validation(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();

        $this->post('/sales/followups', $this->followUpPayload())->assertSessionHasErrors(['related_type', 'related_id']);
        $this->post('/sales/followups', $this->followUpPayload(['related_type' => 'lead', 'related_id' => 99999]))->assertSessionHasErrors('related_id');
        $this->post('/sales/followups', $this->followUpPayload(['related_type' => 'lead', 'related_id' => $lead->id, 'purpose' => 'gossip']))->assertSessionHasErrors('purpose');
        $this->post('/sales/followups', $this->followUpPayload(['related_type' => 'lead', 'related_id' => $lead->id, 'due_at' => '']))->assertSessionHasErrors('due_at');
        $this->assertSame(0, FollowUp::count());
    }

    public function test_overdue_today_and_upcoming_buckets(): void
    {
        $this->actingAs($this->superAdmin());
        Carbon::setTestNow('2026-11-07 12:00');
        $lead = $this->lead();
        $make = fn (string $when) => FollowUp::create(['lead_id' => $lead->id, 'type' => 'call', 'purpose' => 'general', 'due_at' => $when]);

        $overdue = $make('2026-11-06 23:00');
        $earlierToday = $make('2026-11-07 09:00');
        $laterToday = $make('2026-11-07 18:00');
        $upcoming = $make('2026-11-08 09:00');

        $this->assertSame([$overdue->id], FollowUp::overdue()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$earlierToday->id, $laterToday->id], FollowUp::dueToday()->pluck('id')->all());
        $this->assertSame([$upcoming->id], FollowUp::upcoming()->pluck('id')->all());
        $this->assertTrue($overdue->isOverdue());
        $this->assertSame('Overdue', $overdue->statusLabel());
        $this->assertFalse($earlierToday->isOverdue(), 'Due earlier today is not overdue until the day is over');

        $counts = $this->get('/sales/followups')->assertOk()->viewData('counts');
        $this->assertSame(['overdue' => 1, 'today' => 2, 'upcoming' => 1], $counts);
        $this->assertCount(1, $this->get('/sales/followups?tab=overdue')->viewData('followUps'));
        $this->assertCount(2, $this->get('/sales/followups?tab=today')->viewData('followUps'));
        $this->assertCount(4, $this->get('/sales/followups')->viewData('followUps'));

        Carbon::setTestNow();
    }

    public function test_the_day_rolls_over_at_midnight_india_time_not_utc(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));

        // 8:00 pm UTC on 7 Nov is already 1:30 am on 8 Nov in India.
        Carbon::setTestNow(Carbon::parse('2026-11-07 20:00', 'UTC'));
        $this->assertSame('2026-11-08 01:30', now()->format('Y-m-d H:i'));

        $lead = $this->lead();
        $yesterday = FollowUp::create(['lead_id' => $lead->id, 'type' => 'call', 'purpose' => 'general', 'due_at' => '2026-11-07 18:00']);
        $today = FollowUp::create(['lead_id' => $lead->id, 'type' => 'call', 'purpose' => 'general', 'due_at' => '2026-11-08 10:00']);

        $this->assertSame([$yesterday->id], FollowUp::overdue()->pluck('id')->all());
        $this->assertSame([$today->id], FollowUp::dueToday()->pluck('id')->all());

        // A time typed into the form comes back unchanged.
        $this->actingAs($this->superAdmin());
        $this->post('/sales/followups', ['related_type' => 'lead', 'related_id' => $lead->id, 'type' => 'call', 'purpose' => 'general', 'due_at' => '2026-11-09T10:00'])->assertSessionHasNoErrors();
        $this->assertSame('09 Nov 2026, 10:00 AM', FollowUp::latest('id')->first()->due_at->format('d M Y, h:i A'));

        Carbon::setTestNow();
    }

    public function test_completing_records_outcome_books_the_next_and_marks_a_new_lead_contacted(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $product = $this->product();
        $lead = $this->lead();
        $this->assertSame('new', $lead->status);
        $followUp = FollowUp::create(['lead_id' => $lead->id, 'product_id' => $product->id, 'type' => 'visit', 'purpose' => 'sample_dispatch', 'due_at' => now(), 'assigned_to' => $admin->id]);

        $this->post("/sales/followups/{$followUp->id}/complete", [])->assertSessionHasErrors('outcome');
        $this->post("/sales/followups/{$followUp->id}/complete", ['outcome' => 'x', 'next_due_at' => now()->subDay()->toDateTimeString()])->assertSessionHasErrors('next_due_at');
        $this->assertTrue($followUp->fresh()->isPending());

        $this->post("/sales/followups/{$followUp->id}/complete", [
            'outcome' => 'Sample handed over', 'next_due_at' => now()->addDays(3)->toDateTimeString(), 'next_purpose' => 'sample_feedback', 'next_agenda' => 'Leak test result?',
        ])->assertSessionHasNoErrors();

        $followUp->refresh();
        $this->assertSame('done', $followUp->status);
        $this->assertSame('Sample handed over', $followUp->outcome);
        $this->assertSame($admin->id, $followUp->completed_by);
        $this->assertNotNull($followUp->completed_at);
        $this->assertSame('contacted', $lead->fresh()->status);

        $next = FollowUp::pending()->sole();
        $this->assertSame($lead->id, $next->lead_id);
        $this->assertSame($product->id, $next->product_id);
        $this->assertSame('visit', $next->type);
        $this->assertSame('sample_feedback', $next->purpose);
        $this->assertSame('Leak test result?', $next->agenda);
        $this->assertSame($admin->id, $next->assigned_to);

        // A closed follow-up stays closed, and only a lead still "new" moves.
        $this->post("/sales/followups/{$followUp->id}/complete", ['outcome' => 'again'])->assertSessionHas('error');
        $this->assertSame('Sample handed over', $followUp->fresh()->outcome);
    }

    public function test_completing_does_not_downgrade_a_qualified_lead(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead(['status' => 'qualified']);
        $followUp = FollowUp::create(['lead_id' => $lead->id, 'type' => 'call', 'purpose' => 'general', 'due_at' => now()]);

        $this->post("/sales/followups/{$followUp->id}/complete", ['outcome' => 'Spoke to them']);

        $this->assertSame('qualified', $lead->fresh()->status);
    }

    public function test_booking_the_next_follow_up_needs_create_rights(): void
    {
        $lead = $this->lead();
        $followUp = FollowUp::create(['lead_id' => $lead->id, 'type' => 'call', 'purpose' => 'general', 'due_at' => now()]);
        $this->actingAs($this->subAdminWith(['sales_followups' => ['view', 'edit']]));

        $this->post("/sales/followups/{$followUp->id}/complete", ['outcome' => 'x', 'next_due_at' => now()->addDay()->toDateTimeString()])->assertForbidden();
        $this->assertTrue($followUp->fresh()->isPending());

        $this->post("/sales/followups/{$followUp->id}/complete", ['outcome' => 'x'])->assertRedirect();
        $this->assertSame('done', $followUp->fresh()->status);
    }

    public function test_cancel_edit_and_delete_follow_up(): void
    {
        $this->actingAs($this->superAdmin());
        $lead = $this->lead();
        $followUp = FollowUp::create(['lead_id' => $lead->id, 'type' => 'call', 'purpose' => 'general', 'due_at' => now()]);

        $this->get("/sales/followups/{$followUp->id}")->assertOk()->assertSee('Complete this follow-up');
        $this->get("/sales/followups/{$followUp->id}/edit")->assertOk();
        $this->put("/sales/followups/{$followUp->id}", $this->followUpPayload(['related_type' => 'lead', 'related_id' => $lead->id, 'type' => 'email']))->assertSessionHasNoErrors();
        $this->assertSame('email', $followUp->fresh()->type);

        $this->post("/sales/followups/{$followUp->id}/cancel")->assertSessionHas('success');
        $this->assertSame('cancelled', $followUp->fresh()->status);
        $this->post("/sales/followups/{$followUp->id}/cancel")->assertSessionHas('error');
        $this->get("/sales/followups/{$followUp->id}")->assertOk()->assertDontSee('Complete this follow-up');

        $this->delete("/sales/followups/{$followUp->id}")->assertRedirect('/sales/followups');
        $this->assertSame(0, FollowUp::count());
    }

    public function test_follow_ups_filter_by_product_purpose_and_assignee(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $product = $this->product();
        $lead = $this->lead();
        FollowUp::create(['lead_id' => $lead->id, 'product_id' => $product->id, 'type' => 'call', 'purpose' => 'reorder', 'due_at' => now(), 'assigned_to' => $admin->id]);
        FollowUp::create(['lead_id' => $lead->id, 'type' => 'email', 'purpose' => 'general', 'due_at' => now()]);

        $this->assertCount(1, $this->get("/sales/followups?product_id={$product->id}")->viewData('followUps'));
        $this->assertCount(1, $this->get('/sales/followups?purpose=reorder')->viewData('followUps'));
        $this->assertCount(1, $this->get('/sales/followups?type=email')->viewData('followUps'));
        $this->assertCount(1, $this->get('/sales/followups?assigned=me')->viewData('followUps'));
    }

    // ------------------------------------------------------------- Customer retention

    public function test_customer_crud_and_validation(): void
    {
        $this->actingAs($this->superAdmin());
        $payload = ['company_name' => 'Old Client', 'phone' => '9000000005', 'status' => 'active', 'industry' => 'pharma', 'buyer_type' => 'brand_owner', 'reorder_cycle_days' => 30];

        $this->post('/sales/retention', ['company_name' => 'No Phone', 'status' => 'active'])->assertSessionHasErrors('phone');
        $this->post('/sales/retention', ['reorder_cycle_days' => 0] + $payload)->assertSessionHasErrors('reorder_cycle_days');
        $this->post('/sales/retention', ['gstin' => 'BAD'] + $payload)->assertSessionHasErrors('gstin');
        $this->assertSame(0, Customer::count());

        $this->post('/sales/retention', $payload)->assertRedirect();
        $customer = Customer::sole();
        $this->assertSame('Pharmaceutical', $customer->industryLabel());

        $this->get("/sales/retention/{$customer->id}/edit")->assertOk();
        $this->put("/sales/retention/{$customer->id}", ['status' => 'inactive'] + $payload)->assertSessionHasNoErrors();
        $this->assertSame('inactive', $customer->fresh()->status);

        $this->delete("/sales/retention/{$customer->id}")->assertRedirect('/sales/retention');
        $this->assertSame(0, Customer::count());
    }

    public function test_customer_with_orders_or_quotations_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin());

        $withOrder = $this->customer();
        $this->order($withOrder, '2026-10-01');
        $this->delete("/sales/retention/{$withOrder->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $withOrder->id]);

        $withQuote = $this->customer(['phone' => '9000000007']);
        $this->quotation($withQuote);
        $this->delete("/sales/retention/{$withQuote->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $withQuote->id]);
    }

    public function test_repeat_order_can_be_recorded_for_a_product(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $product = $this->product();

        $this->post("/sales/retention/{$customer->id}/orders", ['order_date' => today()->toDateString(), 'quantity' => 100, 'unit' => 'pcs', 'rate' => 2])->assertSessionHasErrors('description');
        $this->post("/sales/retention/{$customer->id}/orders", ['product_id' => $product->id, 'order_date' => today()->addDay()->toDateString(), 'quantity' => 100, 'unit' => 'pcs', 'rate' => 2])->assertSessionHasErrors('order_date');
        $this->post("/sales/retention/{$customer->id}/orders", ['product_id' => $product->id, 'order_date' => today()->toDateString(), 'quantity' => 0, 'unit' => 'pcs', 'rate' => 2])->assertSessionHasErrors('quantity');
        $this->assertSame(0, CustomerOrder::count());

        $this->post("/sales/retention/{$customer->id}/orders", ['product_id' => $product->id, 'order_date' => today()->toDateString(), 'quantity' => 15000, 'unit' => 'pcs', 'rate' => 2.5, 'po_number' => 'PO-1'])->assertSessionHasNoErrors();

        $order = CustomerOrder::sole();
        $this->assertSame($product->name, $order->description);
        $this->assertSame('37500.00', $order->amount);
        $this->get("/sales/retention/{$customer->id}")->assertOk()->assertSee($product->name)->assertSee('PO-1');

        $this->delete("/sales/retention/{$customer->id}/orders/{$order->id}")->assertSessionHas('success');
        $this->assertSame(0, CustomerOrder::count());
    }

    public function test_an_order_cannot_be_removed_through_another_customer(): void
    {
        $this->actingAs($this->superAdmin());
        $order = $this->order($this->customer(), '2026-10-01');
        $other = $this->customer(['phone' => '9000000008']);

        $this->delete("/sales/retention/{$other->id}/orders/{$order->id}")->assertNotFound();
        $this->assertSame(1, CustomerOrder::count());
    }

    public function test_health_list_ranks_and_filters_customers(): void
    {
        $this->actingAs($this->superAdmin());
        Carbon::setTestNow('2026-11-07 12:00');
        $make = fn (string $name, ?int $days) => tap($this->customer(['company_name' => $name, 'reorder_cycle_days' => 30]), function ($c) use ($days) {
            if ($days !== null) {
                $this->order($c, today()->subDays($days)->toDateString());
            }
        });
        $make('Healthy Co', 10);
        $make('Due Co', 35);
        $make('Risk Co', 60);
        $make('Gone Co', 120);
        $make('Fresh Co', null);

        $response = $this->get('/sales/retention')->assertOk();
        $this->assertEquals(['healthy' => 1, 'due' => 1, 'at_risk' => 1, 'lost' => 1, 'new' => 1], $response->viewData('byHealth')->all());
        $this->assertSame(
            ['Gone Co', 'Risk Co', 'Due Co', 'Healthy Co', 'Fresh Co'],
            $response->viewData('customers')->getCollection()->pluck('company_name')->all(),
            'Longest overdue first, no-order customers last',
        );

        $this->get('/sales/retention?health=at_risk')->assertSee('Risk Co')->assertDontSee('Healthy Co');

        Carbon::setTestNow();
    }

    public function test_repeat_rate_and_value_stats(): void
    {
        $this->actingAs($this->superAdmin());
        $repeat = $this->customer();
        $this->order($repeat, '2026-09-01');
        $this->order($repeat, '2026-10-01');
        $once = $this->customer(['phone' => '9000000009']);
        $this->order($once, '2026-10-01', ['amount' => 500]);
        $sameDay = $this->customer(['phone' => '9000000010']);
        $this->order($sameDay, '2026-10-01');
        $this->order($sameDay, '2026-10-01');

        $stats = $this->get('/sales/retention')->viewData('stats');

        $this->assertSame(3, $stats['customers']);
        $this->assertSame(33, $stats['repeat_rate'], 'Two products on one date is one order, so only one of three is a repeat buyer');
        $this->assertSame(8500.0, (float) $stats['value']);
    }

    public function test_pagination_keeps_the_filter(): void
    {
        $this->actingAs($this->superAdmin());
        foreach (range(1, 21) as $i) {
            $this->customer(['company_name' => "Buyer $i", 'phone' => '90000'.str_pad((string) $i, 5, '0', STR_PAD_LEFT)]);
        }

        $customers = $this->get('/sales/retention?health=new')->assertOk()->assertSee('health=new&amp;page=2', false)->viewData('customers');
        $this->assertSame(21, $customers->total());
        $this->assertCount(20, $customers);
        $this->assertCount(1, $this->get('/sales/retention?health=new&page=2')->viewData('customers'));
    }

    public function test_product_history_shows_what_each_customer_buys_and_when_next(): void
    {
        $this->actingAs($this->superAdmin());
        Carbon::setTestNow('2026-11-07 12:00');
        $product = $this->product();
        $customer = $this->customer();
        $this->order($customer, '2026-08-01', ['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1000, 'rate' => 2, 'amount' => 2000]);
        $this->order($customer, '2026-09-01', ['product_id' => $product->id, 'description' => $product->name, 'quantity' => 3000, 'rate' => 2.5, 'amount' => 7500]);
        $this->order($customer, '2026-11-01', ['description' => 'Custom jar', 'amount' => 100]);

        $history = $customer->fresh('orders')->productHistory();

        $this->assertCount(2, $history);
        $top = $history->first();
        $this->assertSame($product->name, $top['description']);
        $this->assertSame(2, $top['orders']);
        $this->assertSame(4000, $top['quantity']);
        $this->assertSame(9500.0, $top['amount']);
        $this->assertSame('2.500', $top['last_rate']);
        $this->assertSame('2026-09-01', $top['last_order']->toDateString());
        $this->assertSame(31, $top['cycle']);
        $this->assertSame('2026-10-02', $top['next_expected']->toDateString());
        $this->assertSame('at_risk', $top['health']);
        $this->assertSame('Custom jar', $history->last()['description']);

        $this->get("/sales/retention?product_id={$product->id}")->assertSee('Regular Buyer');
        $this->get('/sales/retention?product_id='.$this->product(['capacity_ml' => 900])->id)->assertDontSee('Regular Buyer');

        Carbon::setTestNow();
    }

    public function test_reorder_reminders_are_booked_once_for_those_due_or_at_risk(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        Carbon::setTestNow('2026-11-07 12:00');
        $owner = User::factory()->create();
        $product = $this->product();

        $due = $this->customer(['company_name' => 'Due Co', 'reorder_cycle_days' => 30, 'assigned_to' => $owner->id]);
        $this->order($due, '2026-10-01', ['product_id' => $product->id, 'description' => $product->name]);
        $risk = $this->customer(['company_name' => 'Risk Co', 'phone' => '9000000011', 'reorder_cycle_days' => 30]);
        $this->order($risk, '2026-09-01');
        $healthy = $this->customer(['company_name' => 'Healthy Co', 'phone' => '9000000012', 'reorder_cycle_days' => 30]);
        $this->order($healthy, '2026-11-01');
        $lapsed = $this->customer(['company_name' => 'Gone Co', 'phone' => '9000000013', 'reorder_cycle_days' => 30]);
        $this->order($lapsed, '2026-01-01');
        $inactive = $this->customer(['company_name' => 'Off Co', 'phone' => '9000000014', 'reorder_cycle_days' => 30, 'status' => 'inactive']);
        $this->order($inactive, '2026-10-01');
        $booked = $this->customer(['company_name' => 'Booked Co', 'phone' => '9000000015', 'reorder_cycle_days' => 30]);
        $this->order($booked, '2026-10-01');
        FollowUp::create(['customer_id' => $booked->id, 'type' => 'call', 'purpose' => 'reorder', 'due_at' => now()->addDay()]);

        $this->post('/sales/retention-reminders')->assertRedirect()->assertSessionHas('success', '2 reorder follow-up(s) scheduled for today.');

        $created = FollowUp::where('customer_id', $due->id)->sole();
        $this->assertSame('reorder', $created->purpose);
        $this->assertSame($owner->id, $created->assigned_to);
        $this->assertSame($product->id, $created->product_id);
        $this->assertSame($admin->id, $created->created_by);
        $this->assertTrue($created->due_at->isToday());
        $this->assertStringContainsString('37 days ago', $created->agenda);
        $this->assertStringContainsString('usual cycle 30 days', $created->agenda);
        $this->assertSame($admin->id, FollowUp::where('customer_id', $risk->id)->sole()->assigned_to);
        foreach ([$healthy, $lapsed, $inactive] as $skipped) {
            $this->assertSame(0, FollowUp::where('customer_id', $skipped->id)->count());
        }

        // Pressing it again adds nothing.
        $this->post('/sales/retention-reminders')->assertSessionHas('success', 'Nobody new needs a reorder reminder.');
        $this->assertSame(3, FollowUp::count());

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------- End to end

    public function test_the_whole_journey_from_enquiry_to_reorder(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product(['mould_id' => $this->mould()->id]);

        $this->post('/sales/leads', $this->leadPayload(['source' => 'exhibition', 'items' => [[
            'product_id' => $product->id, 'closure' => 'cap', 'decoration' => 'label', 'fill_product' => 'Mustard oil',
            'quantity' => 120000, 'qty_basis' => 'per_month', 'target_price' => 2.4,
        ]]]))->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $this->assertSame('new', $lead->status);

        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload())->assertSessionHasNoErrors();
        $this->assertSame('qualified', $lead->fresh()->status);

        $q = $this->quotation($lead, ['items' => [$this->quotationLine(['product_id' => $product->id, 'quantity' => 120000, 'rate' => 2.5])]]);
        $this->assertSame('quoted', $lead->fresh()->status);

        $this->post('/sales/followups', $this->followUpPayload(['related_type' => 'quotation', 'related_id' => $q->id]))->assertSessionHasNoErrors();
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);
        $this->post("/sales/followups/".FollowUp::sole()->id.'/complete', ['outcome' => 'Sample approved, PO coming']);

        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'won', 'won_on' => today()->subDays(50)->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('won', $lead->fresh()->status);

        $customer = Customer::sole();
        $this->assertSame('at_risk', tap($customer->load('orders'), fn ($c) => $c->update(['reorder_cycle_days' => 30]))->fresh('orders')->health());

        $this->post('/sales/retention-reminders')->assertSessionHasNoErrors();
        $reminder = FollowUp::where('purpose', 'reorder')->sole();
        $this->assertSame($customer->id, $reminder->customer_id);
        $this->assertSame($product->id, $reminder->product_id);

        $this->get("/sales/retention/{$customer->id}")->assertOk()->assertSee($product->name)->assertSee('At risk');
    }

    public function test_every_page_renders_with_real_data(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product(['mould_id' => $this->mould()->id]);
        $lead = $this->lead([], [['product_id' => $product->id, 'target_price' => 2], ['capacity_ml' => 2000, 'neck_size_mm' => 38, 'closure' => 'pump', 'decoration' => 'embossed']]);
        $lead->update(['address' => 'Plot 1', 'notes' => 'Urgent', 'expected_close_date' => '2026-12-01', 'source_detail' => 'Stall 4']);
        $this->put("/sales/qualification/{$lead->id}", $this->qualificationPayload());
        $q = $this->quotation($lead, ['terms' => 'T', 'remarks' => 'R', 'discount_percent' => 5, 'freight_amount' => 100, 'mould_lead_days' => 30, 'delivery_days' => 10]);
        $this->post("/sales/quotations/{$q->id}/status", ['status' => 'sent']);
        $customer = $this->customer(['address' => 'Plot 2', 'remarks' => 'Good']);
        $this->order($customer, '2026-10-01', ['product_id' => $product->id]);
        $followUp = FollowUp::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'type' => 'call', 'purpose' => 'reorder', 'due_at' => now()->subDays(2), 'agenda' => 'A']);

        foreach ([
            '/sales/leads', '/sales/leads/create', "/sales/leads/{$lead->id}", "/sales/leads/{$lead->id}/edit",
            '/sales/qualification', "/sales/qualification/{$lead->id}",
            '/sales/quotations', '/sales/quotations/create', "/sales/quotations/create?lead={$lead->id}", "/sales/quotations/create?customer={$customer->id}",
            "/sales/quotations/{$q->id}", "/sales/quotations/{$q->id}/print",
            '/sales/followups', '/sales/followups?tab=done', '/sales/followups?tab=all', '/sales/followups/create', "/sales/followups/{$followUp->id}", "/sales/followups/{$followUp->id}/edit",
            '/sales/retention', '/sales/retention/create', "/sales/retention/{$customer->id}", "/sales/retention/{$customer->id}/edit",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_demo_seeder_covers_every_state_and_can_be_rerun_without_touching_real_data(): void
    {
        foreach ([[1000, 'bottle'], [2000, 'bottle'], [500, 'bottle'], [50, 'bottle'], [250, 'bottle'], [1000, 'jar'], [20000, 'jar'], [5000, 'other']] as [$ml, $type]) {
            $this->product(['capacity_ml' => $ml, 'product_type' => $type, 'business_type' => $type === 'other' ? 'trading' : 'manufacturing']);
        }
        $this->actingAs($this->superAdmin());
        $realLead = $this->lead(['company_name' => 'Real Lead', 'notes' => 'Real']);
        $realCustomer = $this->customer(['company_name' => 'Real Customer', 'remarks' => 'Real']);
        $realQuote = $this->quotation($realLead);

        $this->seed(DemoSalesSeeder::class);

        $counts = fn () => [Lead::count(), Customer::count(), Quotation::count(), FollowUp::count(), CustomerOrder::count(), BuyerQualification::count()];
        $first = $counts();

        $this->assertEqualsCanonicalizing(array_keys(Lead::SOURCES), Lead::where('notes', 'like', '[Demo]%')->distinct()->pluck('source')->all());
        $this->assertEqualsCanonicalizing(['A', 'B', 'C', 'D'], BuyerQualification::distinct()->pluck('grade')->all());
        $this->assertEqualsCanonicalizing(['new', 'contacted', 'qualified', 'nurture', 'quoted', 'won', 'lost', 'disqualified'], Lead::distinct()->pluck('status')->all());
        $this->assertEqualsCanonicalizing(array_keys(Quotation::STATUSES), Quotation::where('id', '!=', $realQuote->id)->distinct()->pluck('status')->all());
        $this->assertGreaterThan(0, Quotation::expired()->count());
        $this->assertGreaterThan(0, Quotation::expiringSoon()->count());
        $this->assertGreaterThan(0, FollowUp::overdue()->count());
        $this->assertGreaterThan(0, FollowUp::dueToday()->count());
        $this->assertGreaterThan(0, FollowUp::upcoming()->count());
        $this->assertEqualsCanonicalizing(['pending', 'done', 'cancelled'], FollowUp::distinct()->pluck('status')->all());
        $health = Customer::with('orders')->where('remarks', 'like', '[Demo]%')->get()->map->health()->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['new', 'healthy', 'due', 'at_risk', 'lost'], $health);

        // Totals in the demo add up, including the hand-edited revision.
        foreach (Quotation::with('items')->get() as $q) {
            $expected = \App\Support\QuotationTotals::compute($q->items, $q->discount_percent, $q->freight_amount, $q->gst_percent)['total'];
            $this->assertEqualsWithDelta($expected, (float) $q->total, 0.01, $q->number());
        }

        // Every list and record page renders with it.
        foreach (['/sales/leads', '/sales/qualification?scope=all', '/sales/quotations', '/sales/quotations?status=revised', '/sales/followups?tab=all', '/sales/retention'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (Lead::all() as $lead) {
            $this->get("/sales/leads/{$lead->id}")->assertOk();
        }
        foreach (Quotation::all() as $q) {
            $this->get("/sales/quotations/{$q->id}")->assertOk();
            $this->get("/sales/quotations/{$q->id}/print")->assertOk();
        }
        foreach (Customer::all() as $customer) {
            $this->get("/sales/retention/{$customer->id}")->assertOk();
        }

        // Running it again replaces the demo data rather than doubling it.
        $this->seed(DemoSalesSeeder::class);
        $this->assertSame($first, $counts());

        DemoSalesSeeder::purge();
        $this->assertSame([$realLead->id], Lead::pluck('id')->all());
        $this->assertSame([$realCustomer->id], Customer::pluck('id')->all());
        $this->assertSame([$realQuote->id], Quotation::pluck('id')->all());
        $this->assertSame(0, FollowUp::count());
        $this->assertSame(0, CustomerOrder::count());
        $this->assertSame(8, Product::count());
    }

    public function test_edit_form_round_trips_a_saved_lead_without_changing_it(): void
    {
        $this->actingAs($this->superAdmin());
        $this->post('/sales/leads', $this->leadPayload());
        $lead = Lead::with('items')->sole();
        $before = $lead->items->first()->description;

        $response = $this->get("/sales/leads/{$lead->id}/edit")->assertOk();
        $rows = $response->viewData('rows');

        $this->assertSame('1', $rows[0]['capacity_value']);
        $this->assertSame('l', $rows[0]['capacity_unit']);

        $this->put("/sales/leads/{$lead->id}", $this->leadPayload(['items' => $rows]))->assertSessionHasNoErrors();
        $this->assertSame($before, $lead->fresh('items')->items->first()->description);
    }
}
