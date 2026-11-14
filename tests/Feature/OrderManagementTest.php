<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Lead;
use App\Models\Ledger;
use App\Models\Mould;
use App\Models\Neck;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Voucher;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected const MODULES = ['orders_customers', 'orders_sales', 'orders_approval'];

    protected function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

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

    protected function customer(array $extra = []): Customer
    {
        return Customer::create($extra + ['company_name' => 'Asha Oils', 'phone' => '9000000001', 'status' => 'active']);
    }

    protected function product(array $extra = []): Product
    {
        $mould = Mould::create(['mould_type' => 'blow', 'capacity_ml' => 1000 + Product::count(), 'shape' => 'round', 'cavities' => 2, 'status' => 'active']);

        return Product::create($extra + [
            'bill_name' => '1 L Bottle', 'owner_type' => 'company', 'product_type' => 'bottle', 'business_type' => 'manufacturing',
            'mould_id' => $mould->id, 'material' => 'PET', 'weight_grams' => 32, 'unit' => 'pcs', 'status' => 'active',
        ]);
    }

    protected function payload(Customer $customer, Product $product, array $extra = []): array
    {
        return $extra + [
            'customer_id' => $customer->id,
            'order_date' => today()->toDateString(),
            'price_basis' => 'ex_works',
            'gst_percent' => 18,
            'items' => [['product_id' => $product->id, 'quantity' => 10000, 'unit' => 'pcs', 'rate' => 5]],
        ];
    }

    /** A draft saved through the real form. */
    protected function order(?Customer $customer = null, ?Product $product = null, array $extra = []): SalesOrder
    {
        $this->post('/orders/sales', $this->payload($customer ?? $this->customer(), $product ?? $this->product(), $extra))->assertSessionHasNoErrors()->assertRedirect();

        return SalesOrder::latest('id')->first();
    }

    protected function pending(?Customer $customer = null, ?Product $product = null, array $extra = []): SalesOrder
    {
        $order = $this->order($customer, $product, $extra);
        $this->post("/orders/sales/{$order->id}/submit")->assertSessionHasNoErrors();

        return $order->fresh();
    }

    protected function wonQuotation(Customer $customer, array $lines, array $extra = []): Quotation
    {
        $quotation = new Quotation($extra + ['customer_id' => $customer->id, 'quote_date' => today(), 'valid_until' => today()->addDays(15), 'gst_percent' => 18]);
        $quotation->status = 'sent';
        $quotation->save();
        foreach ($lines as $line) {
            $quotation->items()->create($line + ['closure' => 'none', 'decoration' => 'plain', 'unit' => 'pcs']);
        }
        $quotation->recalculate();
        $quotation->load('items')->changeStatus('won', ['po_number' => 'PO-1', 'won_on' => today()->toDateString()], null);

        return $quotation;
    }

    // ------------------------------------------------------------- Sidebar & rights

    public function test_sidebar_shows_order_management_with_its_three_screens_in_flow_order(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->get('/dashboard')->assertOk()->assertSee('Order Management')->assertSee('data-toggle="group-orders"', false);
        $response->assertSeeInOrder(['Customer Master', 'Sales Orders', 'Order Approval']);
    }

    public function test_migration_gives_customer_master_to_those_who_manage_customers_and_nothing_else(): void
    {
        $migration = require database_path('migrations/2026_11_07_000004_create_order_management_tables.php');
        $user = User::factory()->create();
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'module' => 'sales_retention', 'can_show' => 1, 'can_view' => 1, 'can_create' => 1, 'can_edit' => 0, 'can_delete' => 0]);

        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('user_permissions', ['user_id' => $user->id, 'module' => 'orders_customers', 'can_create' => 1, 'can_edit' => 0]);
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $user->id, 'module' => 'orders_sales']);
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $user->id, 'module' => 'orders_approval']);
    }

    public function test_users_without_rights_are_refused(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $order = $this->order($customer);

        $this->actingAs($this->subAdminWith([]));
        foreach (['/orders/customers', '/orders/customers/create', "/orders/customers/{$customer->id}", "/orders/customers/{$customer->id}/edit",
            '/orders/sales', '/orders/sales/create', "/orders/sales/{$order->id}", "/orders/sales/{$order->id}/edit",
            '/orders/approval', "/orders/approval/{$order->id}"] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/orders/customers', [])->assertForbidden();
        $this->post('/orders/sales', [])->assertForbidden();
        $this->post("/orders/sales/{$order->id}/submit")->assertForbidden();
        $this->post("/orders/sales/{$order->id}/cancel", ['reason' => 'x'])->assertForbidden();
        $this->post("/orders/approval/{$order->id}/approve")->assertForbidden();
        $this->post("/orders/approval/{$order->id}/reject", ['reason' => 'x'])->assertForbidden();
        $this->delete("/orders/sales/{$order->id}")->assertForbidden();
    }

    public function test_show_only_rights_hide_records(): void
    {
        $this->actingAs($this->superAdmin());
        $order = $this->pending($this->customer(['company_name' => 'Secret Buyer']));

        $this->actingAs($this->subAdminWith(array_fill_keys(self::MODULES, [])));
        $this->get('/orders/customers')->assertOk()->assertDontSee('Secret Buyer')->assertDontSee('Add Customer');
        $this->get('/orders/sales')->assertOk()->assertDontSee($order->code)->assertDontSee('New Sales Order');
        $this->get('/orders/approval')->assertOk()->assertDontSee($order->code);
    }

    public function test_seeing_the_queue_is_not_the_same_as_being_able_to_approve(): void
    {
        $this->actingAs($this->superAdmin());
        $order = $this->pending();

        $this->actingAs($this->subAdminWith(['orders_approval' => ['view']]));
        $this->get("/orders/approval/{$order->id}")->assertOk()->assertSee('Checks before approving')->assertDontSee('Approve order');
        $this->post("/orders/approval/{$order->id}/approve")->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);
    }

    // ------------------------------------------------------------- Customer Master

    public function test_customer_master_records_the_full_customer(): void
    {
        $this->actingAs($admin = $this->superAdmin());

        $this->post('/orders/customers', [
            'company_name' => 'Asha Oils', 'contact_person' => 'Asha', 'phone' => '9000000001', 'email' => 'a@example.com', 'city' => 'Pune', 'state' => 'Maharashtra',
            'gstin' => '27abcde1234f1z5', 'pan' => 'abcde1234f', 'address' => 'Bill here', 'shipping_address' => 'Ship here',
            'credit_limit' => 500000, 'credit_days' => 30, 'payment_terms' => 'credit_30', 'industry' => 'edible_oil', 'status' => 'active',
        ])->assertRedirect();

        $customer = Customer::sole();
        $this->assertSame('CUS-0001', $customer->code);
        $this->assertSame('27ABCDE1234F1Z5', $customer->gstin);
        $this->assertSame('ABCDE1234F', $customer->pan);
        $this->assertSame('500000.00', $customer->credit_limit);
        $this->assertSame(30, $customer->credit_days);
        $this->assertSame('Ship here', $customer->deliveryAddress());
        $this->assertSame($admin->id, $customer->created_by);
        $this->assertSame('debtors', $customer->ledger->ledger_group, 'The accounts ledger still comes with the customer');

        $this->get('/orders/customers')->assertOk()->assertSee('CUS-0001')->assertSee('5,00,000');
        $this->get("/orders/customers/{$customer->id}")->assertOk()->assertSee('Ship here')->assertSee('Bill here');
    }

    public function test_customer_validation(): void
    {
        $this->actingAs($this->superAdmin());
        $good = ['company_name' => 'Asha', 'phone' => '9000000001', 'status' => 'active'];

        $this->post('/orders/customers', ['company_name' => ''] + $good)->assertSessionHasErrors('company_name');
        $this->post('/orders/customers', ['phone' => 'call'] + $good)->assertSessionHasErrors('phone');
        $this->post('/orders/customers', ['gstin' => 'BAD'] + $good)->assertSessionHasErrors('gstin');
        $this->post('/orders/customers', ['pan' => 'BAD'] + $good)->assertSessionHasErrors('pan');
        $this->post('/orders/customers', ['credit_limit' => -1] + $good)->assertSessionHasErrors('credit_limit');
        $this->post('/orders/customers', ['credit_days' => 999] + $good)->assertSessionHasErrors('credit_days');
        $this->assertSame(0, Customer::count());
    }

    public function test_customer_master_and_retention_share_the_same_customers_without_overwriting_each_other(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer(['credit_limit' => 100000, 'pan' => 'ABCDE1234F', 'shipping_address' => 'Ship here']);

        $this->get('/orders/customers')->assertSee($customer->code);
        $this->get('/sales/retention')->assertSee($customer->company_name);

        // The retention form doesn't have the credit fields, so saving it must leave them alone.
        $this->put("/sales/retention/{$customer->id}", ['company_name' => 'Renamed', 'phone' => '9000000002', 'status' => 'active'])->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Renamed', $customer->company_name);
        $this->assertSame('100000.00', $customer->credit_limit);
        $this->assertSame('ABCDE1234F', $customer->pan);
        $this->assertSame('Ship here', $customer->shipping_address);
    }

    public function test_customer_with_a_sales_order_cannot_be_deleted_from_either_screen(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $this->order($customer);

        $this->delete("/orders/customers/{$customer->id}")->assertSessionHas('error');
        $this->delete("/sales/retention/{$customer->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);

        $free = $this->customer(['phone' => '9000000002']);
        $this->delete("/orders/customers/{$free->id}")->assertRedirect('/orders/customers');
        $this->assertDatabaseMissing('customers', ['id' => $free->id]);
    }

    public function test_customer_list_filters_and_shows_ledger_balance(): void
    {
        $this->actingAs($this->superAdmin());
        $owing = $this->customer(['company_name' => 'Owing Co', 'industry' => 'pharma']);
        $this->customer(['company_name' => 'Other Co', 'phone' => '9000000002', 'status' => 'inactive']);
        $voucher = new Voucher(['voucher_date' => today()]);
        $voucher->type = 'sales';
        $voucher->post([['ledger_id' => $owing->ledger->id, 'debit' => 1180, 'credit' => 0], ['ledger_id' => Ledger::system('sales')->id, 'debit' => 0, 'credit' => 1180]]);

        $this->get('/orders/customers')->assertSee('Owing Co')->assertDontSee('Other Co')->assertSee('1,180.00 Dr');
        $this->get('/orders/customers?status=inactive')->assertSee('Other Co')->assertDontSee('Owing Co');
        $this->get('/orders/customers?industry=pharma')->assertSee('Owing Co');
        $this->get('/orders/customers?search=Nobody')->assertDontSee('Owing Co');
    }

    // ------------------------------------------------------------- Sales orders

    public function test_order_is_saved_as_a_draft_with_serials_and_totals(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $customer = $this->customer(['address' => 'Bill here']);
        $bottle = $this->product();
        $jar = $this->product(['product_type' => 'bottle', 'colour' => 'Amber']);

        $this->post('/orders/sales', $this->payload($customer, $bottle, [
            'po_number' => 'PO-77', 'required_by' => today()->addDays(10)->toDateString(), 'discount_percent' => 10, 'freight_amount' => 1000,
            'items' => [
                ['product_id' => $bottle->id, 'quantity' => 10000, 'unit' => 'pcs', 'rate' => 2, 'mould_charge' => 50000],
                ['product_id' => $jar->id, 'quantity' => 5000, 'unit' => 'pcs', 'rate' => 4],
            ],
        ]))->assertSessionHasNoErrors();

        $order = SalesOrder::with('items')->sole();
        $this->assertSame('SO-0001', $order->code);
        $this->assertSame('draft', $order->status);
        $this->assertSame($admin->id, $order->created_by);
        $this->assertSame('Bill here', $order->shipping_address, 'Falls back to the customer master');
        $this->assertSame($bottle->code, $order->items[0]->product_code);
        $this->assertSame($bottle->name, $order->items[0]->description);
        $this->assertSame('20000.00', $order->items[0]->amount);
        $this->assertSame('40000.00', $order->subtotal);
        $this->assertSame('4000.00', $order->discount_amount);
        $this->assertSame('50000.00', $order->mould_total);
        $this->assertSame('15660.00', $order->tax_amount);
        $this->assertSame('102660.00', $order->total);

        $this->get("/orders/sales/{$order->id}")->assertOk()->assertSee('SO-0001')->assertSee($bottle->code)->assertSee('PO-77');
    }

    public function test_order_validation(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $product = $this->product();
        $post = fn (array $extra) => $this->post('/orders/sales', $this->payload($customer, $product, $extra));

        $post(['customer_id' => 99999])->assertSessionHasErrors('customer_id');
        $post(['order_date' => ''])->assertSessionHasErrors('order_date');
        $post(['required_by' => today()->subDay()->toDateString()])->assertSessionHasErrors('required_by');
        $post(['price_basis' => 'nonsense'])->assertSessionHasErrors('price_basis');
        $post(['items' => []])->assertSessionHasErrors('items');
        $post(['items' => [['quantity' => 1, 'unit' => 'pcs', 'rate' => 1]]])->assertSessionHasErrors('items.0.product_id');
        $post(['items' => [['product_id' => 99999, 'quantity' => 1, 'unit' => 'pcs', 'rate' => 1]]])->assertSessionHasErrors('items.0.product_id');
        $post(['items' => [['product_id' => $product->id, 'quantity' => 0, 'unit' => 'pcs', 'rate' => 1]]])->assertSessionHasErrors('items.0.quantity');
        $post(['items' => [['product_id' => $product->id, 'quantity' => 1, 'unit' => 'kg', 'rate' => 1]]])->assertSessionHasErrors('items.0.unit');
        $post(['items' => [['product_id' => $product->id, 'quantity' => 1, 'unit' => 'pcs']]])->assertSessionHasErrors('items.0.rate');
        $this->assertSame(0, SalesOrder::count());
    }

    public function test_same_product_twice_on_one_order_is_refused(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product();
        $line = ['product_id' => $product->id, 'quantity' => 1, 'unit' => 'pcs', 'rate' => 1];

        $this->post('/orders/sales', $this->payload($this->customer(), $product, ['items' => [$line, $line]]))->assertSessionHasErrors('items');
    }

    public function test_inactive_customers_and_products_cannot_be_newly_ordered(): void
    {
        $this->actingAs($this->superAdmin());
        $active = $this->customer();
        $product = $this->product();

        $this->post('/orders/sales', $this->payload($this->customer(['phone' => '9000000002', 'status' => 'inactive']), $product))->assertSessionHasErrors('customer_id');
        $this->post('/orders/sales', $this->payload($active, $this->product(['status' => 'inactive', 'colour' => 'Grey'])))->assertSessionHasErrors('items');
        $this->assertSame(0, SalesOrder::count());
    }

    public function test_a_draft_stays_editable_after_its_product_or_customer_goes_inactive(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $product = $this->product();
        $order = $this->order($customer, $product);
        $product->update(['status' => 'inactive']);
        $customer->update(['status' => 'inactive']);

        $this->get("/orders/sales/{$order->id}/edit")->assertOk()->assertSee($product->code)->assertSee($customer->code);
        $this->put("/orders/sales/{$order->id}", $this->payload($customer, $product, ['remarks' => 'Kept']))->assertSessionHasNoErrors();
        $this->assertSame('Kept', $order->fresh()->remarks);
    }

    public function test_one_customer_po_cannot_be_entered_twice(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $product = $this->product();
        $first = $this->order($customer, $product, ['po_number' => 'PO-1']);

        $this->post('/orders/sales', $this->payload($customer, $product, ['po_number' => 'PO-1']))
            ->assertSessionHasErrors(['po_number' => "This customer's PO is already on sales order {$first->code}."]);

        // Another customer's PO-1 is unrelated, saving the same order again is fine, and a cancelled order frees the number.
        $this->order($this->customer(['phone' => '9000000002']), $product, ['po_number' => 'PO-1']);
        $this->put("/orders/sales/{$first->id}", $this->payload($customer, $product, ['po_number' => 'PO-1']))->assertSessionHasNoErrors();
        $first->cancel(null, 'Mistake');
        $this->order($customer, $product, ['po_number' => 'PO-1']);
        $this->assertSame(3, SalesOrder::count());
    }

    public function test_order_keeps_the_serial_and_name_it_was_placed_under(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product();
        $code = $product->code;
        $name = $product->name;
        $order = $this->order(null, $product);

        $product->update(['colour' => 'Amber']);
        $this->assertSame($name, $order->items()->sole()->description);

        $product->delete();
        $line = $order->items()->sole();
        $this->assertNull($line->product_id);
        $this->assertSame($code, $line->product_code);
        $this->assertSame($name, $line->description);
    }

    public function test_only_a_draft_or_rejected_order_can_change_and_only_a_draft_can_be_deleted(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $product = $this->product();
        $order = $this->order($customer, $product);

        $this->put("/orders/sales/{$order->id}", $this->payload($customer, $product, ['items' => [['product_id' => $product->id, 'quantity' => 100, 'unit' => 'pcs', 'rate' => 10]]]))->assertSessionHasNoErrors();
        $this->assertSame('1180.00', $order->fresh()->total);

        $this->post("/orders/sales/{$order->id}/submit");

        $this->get("/orders/sales/{$order->id}/edit")->assertRedirect("/orders/sales/{$order->id}");
        $this->put("/orders/sales/{$order->id}", $this->payload($customer, $product, ['items' => [['product_id' => $product->id, 'quantity' => 1, 'unit' => 'pcs', 'rate' => 1]]]))->assertSessionHas('error');
        $this->assertSame('1180.00', $order->fresh()->total);
        $this->delete("/orders/sales/{$order->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('sales_orders', ['id' => $order->id]);

        $this->post("/orders/approval/{$order->id}/reject", ['reason' => 'Wrong rate']);
        $this->get("/orders/sales/{$order->id}/edit")->assertOk()->assertSee('Wrong rate');
        $this->put("/orders/sales/{$order->id}", $this->payload($customer, $product))->assertSessionHasNoErrors();
        $this->delete("/orders/sales/{$order->id}")->assertSessionHas('error');

        $draft = $this->order($customer, $product);
        $this->delete("/orders/sales/{$draft->id}")->assertRedirect('/orders/sales');
        $this->assertDatabaseMissing('sales_orders', ['id' => $draft->id]);
    }

    public function test_order_list_filters_and_stats(): void
    {
        $this->actingAs($this->superAdmin());
        $asha = $this->customer();
        $ravi = $this->customer(['company_name' => 'Ravi Foods', 'phone' => '9000000002']);
        $bottle = $this->product();
        $jar = $this->product(['colour' => 'Amber']);
        $draft = $this->order($asha, $bottle, ['po_number' => 'PO-A', 'order_date' => '2026-10-01']);
        $approved = $this->pending($ravi, $jar, ['order_date' => '2026-11-01']);
        $this->post("/orders/approval/{$approved->id}/approve");

        $total = fn (string $query) => $this->get("/orders/sales?$query")->assertOk()->viewData('orders')->total();

        $this->assertSame(2, $total(''));
        $this->assertSame(1, $total('status=approved'));
        $this->assertSame(1, $total("customer_id={$ravi->id}"));
        $this->assertSame(1, $total("product_id={$bottle->id}"));
        $this->assertSame(1, $total('from=2026-10-15'));
        $this->assertSame(1, $total('to=2026-10-15'));
        $this->assertSame(1, $total('search=PO-A'));
        $this->assertSame(1, $total('search=Ravi'));

        $stats = $this->get('/orders/sales')->viewData('stats');
        $this->assertSame(1, $stats['draft']);
        $this->assertSame(1, $stats['approved']);
        $this->assertSame((float) $approved->fresh()->total, $stats['approved_value']);
    }

    // ------------------------------------------------------------- Approval

    public function test_submitted_order_waits_in_the_queue_and_approval_records_it(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $product = $this->product();
        $order = $this->order($customer, $product, ['po_number' => 'PO-9', 'discount_percent' => 10]);
        $this->assertSame(0, $this->get('/orders/approval')->viewData('orders')->total());
        $this->assertSame(0, CustomerOrder::count());

        $this->post("/orders/sales/{$order->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('pending', $order->fresh()->status);
        $this->get('/dashboard');
        $this->get('/orders/approval')->assertOk()->assertSee($order->code);
        $this->assertSame(0, CustomerOrder::count(), 'Not business done until approved');

        $this->actingAs($approver = $this->superAdmin());
        $this->post("/orders/approval/{$order->id}/approve", ['note' => 'Good customer'])->assertRedirect('/orders/approval');

        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertSame($approver->id, $order->decided_by);
        $this->assertNotNull($order->decided_at);
        $this->assertSame('Good customer', $order->decision_note);

        $line = CustomerOrder::sole();
        $this->assertSame($customer->id, $line->customer_id);
        $this->assertSame($product->id, $line->product_id);
        $this->assertSame($order->id, $line->sales_order_id);
        $this->assertSame('PO-9', $line->po_number);
        $this->assertSame('4.500', $line->rate, 'Net of the 10% discount');
        $this->assertSame('45000.00', $line->amount);

        $this->assertDatabaseHas('sales_order_logs', ['sales_order_id' => $order->id, 'from_status' => 'pending', 'to_status' => 'approved', 'note' => 'Good customer']);
        $this->get("/orders/sales/{$order->id}")->assertOk()->assertSee('Good customer')->assertSee('Approval trail');
        $this->get("/sales/retention/{$customer->id}")->assertOk()->assertSee($product->name);
    }

    public function test_rejection_needs_a_reason_and_goes_back_for_correction(): void
    {
        $this->actingAs($this->superAdmin());
        $order = $this->pending();

        $this->post("/orders/approval/{$order->id}/reject", [])->assertSessionHasErrors('reason');
        $this->assertSame('pending', $order->fresh()->status);

        $this->post("/orders/approval/{$order->id}/reject", ['reason' => 'Rate too low'])->assertRedirect('/orders/approval');

        $order->refresh();
        $this->assertSame('rejected', $order->status);
        $this->assertSame('Rate too low', $order->decision_note);
        $this->assertSame(0, CustomerOrder::count());
        $this->get("/orders/sales/{$order->id}")->assertSee('Rate too low')->assertSee('Submit for approval');
        $this->get('/orders/approval?tab=rejected')->assertSee($order->code)->assertSee('Rate too low');

        // Resubmitting clears the old decision.
        $this->post("/orders/sales/{$order->id}/submit")->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertNull($order->decision_note);
        $this->assertNull($order->decided_by);
    }

    public function test_every_illegal_move_is_refused(): void
    {
        $this->actingAs($this->superAdmin());
        $draft = $this->order();

        $this->post("/orders/approval/{$draft->id}/approve")->assertSessionHas('error');
        $this->post("/orders/approval/{$draft->id}/reject", ['reason' => 'x'])->assertSessionHas('error');
        $this->assertSame('draft', $draft->fresh()->status);

        $approved = $this->pending($this->customer(['phone' => '9000000002']));
        $this->post("/orders/approval/{$approved->id}/approve");
        $this->post("/orders/approval/{$approved->id}/approve")->assertSessionHas('error');
        $this->post("/orders/approval/{$approved->id}/reject", ['reason' => 'x'])->assertSessionHas('error');
        $this->post("/orders/sales/{$approved->id}/submit")->assertSessionHas('error');
        $this->assertSame(1, CustomerOrder::where('sales_order_id', $approved->id)->count(), 'Approving twice must not double the history');

        $cancelled = $this->order($this->customer(['phone' => '9000000003']));
        $cancelled->cancel(null, 'x');
        $this->expectException(DomainException::class);
        $cancelled->cancel(null, 'again');
    }

    public function test_nobody_approves_their_own_order_except_the_super_admin(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $product = $this->product();

        $maker = $this->subAdminWith(['orders_sales' => ['view', 'create', 'edit'], 'orders_approval' => ['view', 'edit']]);
        $this->actingAs($maker);
        $order = $this->pending($customer, $product);

        $this->get("/orders/approval/{$order->id}")->assertOk()->assertSee('someone else has to approve')->assertDontSee('Approve order');
        $this->post("/orders/approval/{$order->id}/approve")->assertForbidden();
        $this->post("/orders/approval/{$order->id}/reject", ['reason' => 'x'])->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);

        $this->actingAs($this->subAdminWith(['orders_approval' => ['view', 'edit']]));
        $this->get("/orders/approval/{$order->id}")->assertSee('Approve order');
        $this->post("/orders/approval/{$order->id}/approve")->assertRedirect();
        $this->assertSame('approved', $order->fresh()->status);

        // A super admin may be the only person around.
        $this->actingAs($this->superAdmin());
        $own = $this->pending($customer, $product);
        $this->post("/orders/approval/{$own->id}/approve")->assertRedirect();
        $this->assertSame('approved', $own->fresh()->status);
    }

    public function test_over_the_credit_limit_needs_a_written_reason(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer(['credit_limit' => 100000]);
        $voucher = new Voucher(['voucher_date' => today()]);
        $voucher->type = 'sales';
        $voucher->post([['ledger_id' => $customer->ledger->id, 'debit' => 60000, 'credit' => 0], ['ledger_id' => Ledger::system('sales')->id, 'debit' => 0, 'credit' => 60000]]);

        // 10,000 × ₹5 + 18% = 59,000; with the 60,000 already owed that's 1,19,000.
        $order = $this->pending($customer);

        $this->get('/orders/approval')->assertSee('Over by ₹19,000');
        $this->get("/orders/approval/{$order->id}")->assertOk()->assertSee('Over the credit limit')->assertSee('₹19,000.00')->assertSee('Why approve over the limit?');

        $this->post("/orders/approval/{$order->id}/approve", [])->assertSessionHasErrors('note');
        $this->assertSame('pending', $order->fresh()->status);

        $this->post("/orders/approval/{$order->id}/approve", ['note' => 'Payment promised on Friday'])->assertRedirect();
        $this->assertSame('approved', $order->fresh()->status);
        $this->assertSame('Payment promised on Friday', $order->fresh()->decision_note);
    }

    public function test_within_limit_and_no_limit_need_no_reason(): void
    {
        $this->actingAs($this->superAdmin());

        $within = $this->pending($this->customer(['credit_limit' => 1000000]));
        $this->get("/orders/approval/{$within->id}")->assertSee('Within the credit limit');
        $this->post("/orders/approval/{$within->id}/approve")->assertRedirect();
        $this->assertSame('approved', $within->fresh()->status);

        $unlimited = $this->pending($this->customer(['phone' => '9000000002']));
        $this->get("/orders/approval/{$unlimited->id}")->assertSee('No credit limit is set');
        $this->post("/orders/approval/{$unlimited->id}/approve")->assertRedirect();
        $this->assertSame('approved', $unlimited->fresh()->status);
    }

    public function test_approver_is_warned_about_what_needs_a_look(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $noMould = Product::create(['bill_name' => 'x', 'owner_type' => 'company', 'product_type' => 'bottle', 'business_type' => 'manufacturing', 'capacity_ml' => 750, 'unit' => 'pcs', 'status' => 'active']);
        $inactive = $this->product();
        $traded = Product::create(['bill_name' => 'y', 'owner_type' => 'company', 'product_type' => 'jar', 'business_type' => 'trading', 'capacity_ml' => 200, 'unit' => 'pcs', 'status' => 'active']);
        $order = $this->pending($customer, $noMould, ['required_by' => today()->toDateString(), 'items' => [
            ['product_id' => $noMould->id, 'quantity' => 1, 'unit' => 'pcs', 'rate' => 0],
            ['product_id' => $inactive->id, 'quantity' => 1, 'unit' => 'pcs', 'rate' => 1],
            ['product_id' => $traded->id, 'quantity' => 1, 'unit' => 'pcs', 'rate' => 1],
        ]]);
        $inactive->update(['status' => 'inactive']);
        $customer->update(['status' => 'inactive']);
        $order->forceFill(['required_by' => today()->subDay()])->save();

        $this->get("/orders/approval/{$order->id}")->assertOk()
            ->assertSee("{$noMould->code} has no mould linked", false)
            ->assertSee("{$noMould->code} is priced at zero")
            ->assertSee("{$inactive->code} is marked Inactive")
            ->assertSee('This customer is marked Inactive')
            ->assertSee('required-by date has already passed')
            ->assertDontSee("{$traded->code} has no mould linked", false);
    }

    public function test_approval_page_shows_the_last_rate_the_customer_paid(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $product = $this->product();
        $first = $this->pending($customer, $product);
        $this->get("/orders/approval/{$first->id}")->assertSee('First order');
        $this->post("/orders/approval/{$first->id}/approve");

        $second = $this->pending($customer, $product, ['items' => [['product_id' => $product->id, 'quantity' => 100, 'unit' => 'pcs', 'rate' => 7]]]);

        $this->get("/orders/approval/{$second->id}")->assertOk()->assertSee('₹5.000')->assertSee('₹7.000');
    }

    public function test_approval_tabs_show_the_right_orders(): void
    {
        $this->actingAs($this->superAdmin());
        $pending = $this->pending($this->customer(['phone' => '9000000002']));
        $approved = $this->pending($this->customer(['phone' => '9000000003']));
        $this->post("/orders/approval/{$approved->id}/approve");
        $rejected = $this->pending($this->customer(['phone' => '9000000004']));
        $this->post("/orders/approval/{$rejected->id}/reject", ['reason' => 'No']);
        $draft = $this->order($this->customer(['phone' => '9000000005']));
        $this->get('/dashboard');

        $ids = fn (string $tab) => $this->get("/orders/approval?tab=$tab")->assertOk()->viewData('orders')->pluck('id')->all();

        $this->assertSame([$pending->id], $ids('pending'));
        $this->assertSame([$approved->id], $ids('approved'));
        $this->assertSame([$rejected->id], $ids('rejected'));
        $this->assertEquals(['pending' => 1, 'approved' => 1, 'rejected' => 1], $this->get('/orders/approval')->viewData('counts')->all());
        $this->get('/orders/approval?tab=nonsense')->assertNotFound();
        $this->assertNotContains($draft->id, $ids('pending'));
    }

    // ------------------------------------------------------------- Cancelling

    public function test_cancel_needs_a_reason_and_approved_orders_leave_the_history(): void
    {
        $this->actingAs($this->superAdmin());
        $order = $this->pending();
        $this->post("/orders/approval/{$order->id}/approve");
        $this->assertSame(1, CustomerOrder::count());

        $this->post("/orders/sales/{$order->id}/cancel", [])->assertSessionHasErrors('reason');
        $this->assertSame('approved', $order->fresh()->status);

        $this->post("/orders/sales/{$order->id}/cancel", ['reason' => 'Buyer withdrew'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, CustomerOrder::count());
        $this->assertDatabaseHas('sales_order_logs', ['sales_order_id' => $order->id, 'to_status' => 'cancelled', 'note' => 'Buyer withdrew']);
        $this->post("/orders/sales/{$order->id}/cancel", ['reason' => 'Again'])->assertSessionHas('error');
    }

    public function test_only_an_approver_can_cancel_an_approved_order(): void
    {
        $this->actingAs($this->superAdmin());
        $approved = $this->pending();
        $this->post("/orders/approval/{$approved->id}/approve");
        $draft = $this->order($this->customer(['phone' => '9000000002']));

        $this->actingAs($this->subAdminWith(['orders_sales' => ['view', 'edit']]));
        $this->get("/orders/sales/{$approved->id}")->assertOk()->assertDontSee('Cancel this order');
        $this->post("/orders/sales/{$approved->id}/cancel", ['reason' => 'x'])->assertForbidden();
        $this->assertSame('approved', $approved->fresh()->status);

        $this->get("/orders/sales/{$draft->id}")->assertSee('Cancel this order');
        $this->post("/orders/sales/{$draft->id}/cancel", ['reason' => 'x'])->assertRedirect();
        $this->assertSame('cancelled', $draft->fresh()->status);
    }

    // ------------------------------------------------------------- From a won quotation

    public function test_order_starts_from_a_won_quotation_and_does_not_double_count_the_sale(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer(['address' => 'Bill here']);
        $product = $this->product();
        $quotation = $this->wonQuotation($customer, [['product_id' => $product->id, 'product_type' => 'bottle', 'quantity' => 2000, 'rate' => 3, 'mould_charge' => 500]], ['packing' => 'Box of 100', 'discount_percent' => 5, 'payment_terms' => 'credit_30']);
        $this->assertSame(1, CustomerOrder::where('quotation_id', $quotation->id)->count(), 'Winning logged an estimate');

        $this->get("/sales/quotations/{$quotation->id}")->assertSee('Create sales order');

        $response = $this->get("/orders/sales/create?quotation={$quotation->id}")->assertOk()->assertSee('Box of 100');
        $rows = $response->viewData('rows');
        $this->assertSame($product->id, $rows[0]['product_id']);
        $this->assertSame(2000, $rows[0]['quantity']);
        $this->assertSame(3.0, $rows[0]['rate']);
        $this->assertSame(500.0, $rows[0]['mould_charge']);
        $this->assertSame('PO-1', $response->viewData('order')->po_number);
        $this->assertSame('credit_30', $response->viewData('order')->payment_terms);

        // The buyer's PO came in for less than was quoted.
        $order = $this->pending($customer, $product, ['quotation_id' => $quotation->id, 'po_number' => 'PO-1', 'items' => [['product_id' => $product->id, 'quantity' => 1500, 'unit' => 'pcs', 'rate' => 3]]]);
        $this->assertSame($quotation->id, $order->quotation_id);
        $this->assertSame(1, CustomerOrder::where('quotation_id', $quotation->id)->count(), 'Nothing changes until approval');
        $this->assertEquals(2000, CustomerOrder::sole()->quantity);

        $this->post("/orders/approval/{$order->id}/approve")->assertRedirect();

        $line = CustomerOrder::sole();
        $this->assertEquals(1500, $line->quantity, 'The approved order replaces the estimate');
        $this->assertSame($order->id, $line->sales_order_id);
        $this->assertSame($quotation->id, $line->quotation_id);

        $this->get("/sales/quotations/{$quotation->id}")->assertDontSee('Create sales order')->assertSee($order->code);
    }

    public function test_only_a_won_quotation_without_a_live_order_can_become_one(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $product = $this->product();

        $draftQuote = new Quotation(['customer_id' => $customer->id, 'quote_date' => today(), 'valid_until' => today()]);
        $draftQuote->status = 'draft';
        $draftQuote->save();
        $this->get("/orders/sales/create?quotation={$draftQuote->id}")->assertRedirect("/sales/quotations/{$draftQuote->id}");
        $this->post('/orders/sales', $this->payload($customer, $product, ['quotation_id' => $draftQuote->id]))->assertSessionHasErrors('customer_id');

        $quotation = $this->wonQuotation($customer, [['product_id' => $product->id, 'product_type' => 'bottle', 'quantity' => 1, 'rate' => 1]]);

        $other = $this->customer(['phone' => '9000000002']);
        $this->post('/orders/sales', $this->payload($other, $product, ['quotation_id' => $quotation->id]))->assertSessionHasErrors('customer_id');

        $first = $this->order($customer, $product, ['quotation_id' => $quotation->id]);
        $this->get("/orders/sales/create?quotation={$quotation->id}")->assertRedirect("/sales/quotations/{$quotation->id}")->assertSessionHas('error', fn ($m) => str_contains($m, $first->code));
        $this->post('/orders/sales', $this->payload($customer, $product, ['quotation_id' => $quotation->id]))->assertSessionHasErrors('customer_id');

        // Cancelling frees the quotation up again.
        $first->cancel(null, 'Mistake');
        $this->get("/orders/sales/create?quotation={$quotation->id}")->assertOk();
    }

    public function test_a_custom_spec_line_must_be_tied_to_a_product_before_the_order_saves(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer();
        $quotation = $this->wonQuotation($customer, [['product_type' => 'bottle', 'capacity_ml' => 300, 'material' => 'PET', 'quantity' => 500, 'rate' => 4]]);

        $response = $this->get("/orders/sales/create?quotation={$quotation->id}")->assertOk()
            ->assertSee('On the quotation as a custom spec')->assertSee('300 ml PET Bottle');
        $this->assertNull($response->viewData('rows')[0]['product_id']);

        $this->post('/orders/sales', ['customer_id' => $customer->id, 'quotation_id' => $quotation->id, 'order_date' => today()->toDateString(), 'price_basis' => 'ex_works', 'gst_percent' => 18,
            'items' => [['custom_note' => 'x', 'quantity' => 500, 'unit' => 'pcs', 'rate' => 4]]])->assertSessionHasErrors('items.0.product_id');
    }

    public function test_an_order_for_a_clients_special_neck_carries_that_products_serial(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = Neck::create(['neck_size_mm' => 38, 'status' => 'active']);
        $product = $this->product(['neck_id' => $neck->id, 'weight_grams' => 40]);

        $order = $this->order(null, $product);

        $line = $order->items()->sole();
        $this->assertSame($product->code, $line->product_code);
        $this->assertStringContainsString('38 mm', $line->description);
        $this->assertStringContainsString('40 g', $line->description);
    }

    // ------------------------------------------------------------- Pages

    public function test_new_order_prefills_from_the_customer(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer(['shipping_address' => 'Ship here', 'payment_terms' => 'credit_60']);

        $this->get("/orders/customers/{$customer->id}")->assertSee('New sales order');
        $response = $this->get("/orders/sales/create?customer={$customer->id}")->assertOk()->assertSee('Ship here');
        $this->assertSame('credit_60', $response->viewData('order')->payment_terms);
        $this->get("/sales/retention/{$customer->id}")->assertSee('New orders go in through');
    }

    public function test_every_page_renders(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->customer(['address' => 'A', 'remarks' => 'R', 'credit_limit' => 1000]);
        $product = $this->product();
        $draft = $this->order($customer, $product, ['remarks' => 'R', 'po_number' => 'P1', 'po_date' => today()->toDateString(), 'required_by' => today()->toDateString(), 'packing' => 'Box', 'discount_percent' => 1, 'freight_amount' => 1]);
        $pending = $this->pending($customer, $product);
        $approved = $this->pending($customer, $product);
        $this->post("/orders/approval/{$approved->id}/approve", ['note' => 'OK']);
        $rejected = $this->pending($customer, $product);
        $this->post("/orders/approval/{$rejected->id}/reject", ['reason' => 'No']);
        $cancelled = $this->order($customer, $product);
        $cancelled->cancel(null, 'x');

        foreach (['/orders/customers', '/orders/customers/create', "/orders/customers/{$customer->id}", "/orders/customers/{$customer->id}/edit",
            '/orders/sales', '/orders/sales/create', "/orders/sales/{$draft->id}/edit", "/orders/sales/{$rejected->id}/edit",
            '/orders/approval', '/orders/approval?tab=approved', '/orders/approval?tab=rejected'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach ([$draft, $pending, $approved, $rejected, $cancelled] as $order) {
            $this->get("/orders/sales/{$order->id}")->assertOk();
            $this->get("/orders/approval/{$order->id}")->assertOk();
        }
    }

    public function test_the_whole_flow_from_customer_to_approved_order(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product();

        $this->post('/orders/customers', ['company_name' => 'Flow Foods', 'phone' => '9000000001', 'address' => 'Plot 1', 'credit_limit' => 200000, 'status' => 'active'])->assertRedirect();
        $customer = Customer::sole();

        $this->post('/orders/sales', $this->payload($customer, $product, ['shipping_address' => '']))->assertSessionHasNoErrors();
        $order = SalesOrder::sole();
        $this->assertSame('Plot 1', $order->shipping_address);

        $this->post("/orders/sales/{$order->id}/submit");
        $this->get("/orders/approval/{$order->id}")->assertSee('Within the credit limit');
        $this->post("/orders/approval/{$order->id}/approve");

        $this->assertSame('approved', $order->fresh()->status);
        $history = Customer::with('orders')->sole();
        $this->assertSame(50000.0, $history->lifetimeValue());
        $this->assertSame('healthy', $history->health());
    }
}
