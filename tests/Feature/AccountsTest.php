<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ledger;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    protected const MODULES = ['accounts_ledgers', 'accounts_vouchers', 'accounts_trial_balance'];

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

    protected function ledger(string $group, string $name, array $extra = []): Ledger
    {
        return Ledger::create($extra + ['name' => $name, 'ledger_group' => $group, 'status' => 'active', 'opening_type' => 'dr']);
    }

    /** Saves a voucher through the real form, returning it. */
    protected function voucher(string $type, array $data): Voucher
    {
        $this->post('/accounts/vouchers', $data + ['type' => $type, 'voucher_date' => '2026-11-01'])->assertSessionHasNoErrors()->assertRedirect();

        return Voucher::latest('id')->first();
    }

    protected function lineFor(Voucher $voucher, Ledger $ledger): array
    {
        $line = $voucher->lines()->where('ledger_id', $ledger->id)->sole();

        return [(float) $line->debit, (float) $line->credit];
    }

    protected function balance(Ledger $ledger): float
    {
        return Ledger::whereKey($ledger->id)->withTotals()->sole()->closing();
    }

    // ------------------------------------------------------------- Sidebar & rights

    public function test_sidebar_shows_accounts_with_only_its_three_screens(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->get('/dashboard')->assertOk()->assertSee('data-toggle="group-accounts"', false);
        foreach (['Ledgers', 'Vouchers', 'Trial Balance'] as $label) {
            $response->assertSee($label);
        }
        $this->assertNull(config('modules.accounts'));
        $this->assertCount(3, collect(config('modules'))->where('group', 'accounts'));
    }

    public function test_migration_carries_old_accounts_rights_over_to_every_screen(): void
    {
        $migration = require database_path('migrations/2026_11_07_000002_create_accounts_tables.php');
        $user = User::factory()->create();

        $migration->down();
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'module' => 'accounts', 'can_show' => 1, 'can_view' => 1, 'can_create' => 0, 'can_edit' => 1, 'can_delete' => 0]);
        $migration->up();

        $this->assertDatabaseMissing('user_permissions', ['user_id' => $user->id, 'module' => 'accounts']);
        foreach (self::MODULES as $module) {
            $this->assertDatabaseHas('user_permissions', ['user_id' => $user->id, 'module' => $module, 'can_edit' => 1, 'can_create' => 0]);
        }
    }

    public function test_migration_gives_existing_customers_a_ledger_without_clashing_names(): void
    {
        $migration = require database_path('migrations/2026_11_07_000002_create_accounts_tables.php');
        $migration->down();
        $now = now();
        DB::table('customers')->insert([
            ['code' => 'CUS-0001', 'company_name' => 'Asha Plastics', 'phone' => '9000000001', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CUS-0002', 'company_name' => 'Sales', 'phone' => '9000000002', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $migration->up();

        $this->assertSame('Asha Plastics', Customer::where('code', 'CUS-0001')->sole()->ledger->name);
        $this->assertSame('Sales (CUS-0002)', Customer::where('code', 'CUS-0002')->sole()->ledger->name);
        $this->assertSame(11, Ledger::count());
        $this->assertSame('LED-0012', Ledger::nextCode());
    }

    public function test_users_without_rights_are_refused(): void
    {
        $ledger = Ledger::system('cash');
        $this->actingAs($this->superAdmin());
        $voucher = $this->voucher('contra', ['main_ledger_id' => $ledger->id, 'counter_ledger_id' => $this->ledger('bank', 'HDFC')->id, 'amount' => 10]);

        $this->actingAs($this->subAdminWith([]));
        foreach (['/accounts/ledgers', '/accounts/ledgers/create', "/accounts/ledgers/{$ledger->id}", "/accounts/ledgers/{$ledger->id}/edit",
            '/accounts/vouchers', '/accounts/vouchers/create?type=receipt', "/accounts/vouchers/{$voucher->id}", "/accounts/vouchers/{$voucher->id}/edit", '/accounts/trial-balance'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/accounts/ledgers', [])->assertForbidden();
        $this->post('/accounts/vouchers', [])->assertForbidden();
        $this->delete("/accounts/vouchers/{$voucher->id}")->assertForbidden();
    }

    public function test_each_right_is_enforced_separately(): void
    {
        $bank = $this->ledger('bank', 'HDFC');
        $this->actingAs($this->superAdmin());
        $voucher = $this->voucher('contra', ['main_ledger_id' => Ledger::system('cash')->id, 'counter_ledger_id' => $bank->id, 'amount' => 10]);

        $this->actingAs($this->subAdminWith(array_fill_keys(self::MODULES, [])));
        $this->get('/accounts/ledgers')->assertOk()->assertDontSee('HDFC')->assertSee("You don't have permission to view these records", false);
        $this->get('/accounts/vouchers')->assertOk()->assertDontSee($voucher->code);
        $this->get('/accounts/trial-balance')->assertForbidden();

        $this->actingAs($this->subAdminWith(['accounts_ledgers' => ['view'], 'accounts_vouchers' => ['view'], 'accounts_trial_balance' => ['view']]));
        $this->get('/accounts/ledgers')->assertOk()->assertSee('HDFC')->assertDontSee('Add Ledger');
        $this->get("/accounts/vouchers/{$voucher->id}")->assertOk()->assertDontSee('>Edit', false);
        $this->get('/accounts/trial-balance')->assertOk();
        $this->get("/accounts/vouchers/{$voucher->id}/edit")->assertForbidden();
    }

    // ------------------------------------------------------------- Ledgers

    public function test_built_in_ledgers_exist_for_vouchers_to_post_to(): void
    {
        foreach (['cash', 'sales', 'purchase', 'output_cgst', 'output_sgst', 'output_igst', 'input_cgst', 'input_sgst', 'input_igst'] as $key) {
            $this->assertTrue(Ledger::system($key)->isSystem());
        }
    }

    public function test_every_type_of_ledger_can_be_recorded(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (array_keys(Ledger::GROUPS) as $group) {
            $this->post('/accounts/ledgers', ['name' => "Ledger $group", 'ledger_group' => $group, 'opening_type' => 'dr', 'status' => 'active'])->assertSessionHasNoErrors();
            $this->assertSame($group, Ledger::where('name', "Ledger $group")->sole()->ledger_group);
        }

        $this->get('/accounts/ledgers?group=fixed_assets')->assertOk()->assertSee('Ledger fixed_assets')->assertDontSee('Ledger capital');
        $this->get('/accounts/ledgers/create')->assertOk()->assertSee('Secured Loans')->assertSee('Duties &amp; Taxes (GST, TDS)', false);
    }

    public function test_ledger_validation(): void
    {
        $this->actingAs($this->superAdmin());
        $good = ['name' => 'ABC Resins', 'ledger_group' => 'creditors', 'opening_type' => 'cr', 'status' => 'active'];

        $this->post('/accounts/ledgers', ['name' => ''] + $good)->assertSessionHasErrors('name');
        $this->post('/accounts/ledgers', ['ledger_group' => 'nonsense'] + $good)->assertSessionHasErrors('ledger_group');
        $this->post('/accounts/ledgers', ['name' => 'Cash in Hand'] + $good)->assertSessionHasErrors('name');
        $this->post('/accounts/ledgers', ['opening_balance' => -5] + $good)->assertSessionHasErrors('opening_balance');
        $this->post('/accounts/ledgers', ['opening_type' => 'xx'] + $good)->assertSessionHasErrors('opening_type');
        $this->post('/accounts/ledgers', ['gstin' => 'BAD'] + $good)->assertSessionHasErrors('gstin');
        $this->post('/accounts/ledgers', ['pan' => 'BAD'] + $good)->assertSessionHasErrors('pan');
        $this->post('/accounts/ledgers', ['ifsc' => 'BAD'] + $good)->assertSessionHasErrors('ifsc');
        $this->assertSame(0, Ledger::where('name', 'ABC Resins')->count());

        $this->post('/accounts/ledgers', ['gstin' => '27abcde1234f1z5', 'pan' => 'abcde1234f'] + $good)->assertSessionHasNoErrors();
        $ledger = Ledger::where('name', 'ABC Resins')->sole();
        $this->assertSame('27ABCDE1234F1Z5', $ledger->gstin);
        $this->assertSame('ABCDE1234F', $ledger->pan);
    }

    public function test_bank_details_are_kept_only_on_bank_ledgers(): void
    {
        $this->actingAs($this->superAdmin());
        $base = ['opening_type' => 'dr', 'status' => 'active', 'bank_name' => 'HDFC', 'account_number' => '123456', 'ifsc' => 'hdfc0001234'];

        $this->post('/accounts/ledgers', $base + ['name' => 'HDFC Current', 'ledger_group' => 'bank'])->assertSessionHasNoErrors();
        $this->post('/accounts/ledgers', $base + ['name' => 'Electricity', 'ledger_group' => 'direct_expenses'])->assertSessionHasNoErrors();

        $this->assertSame('HDFC0001234', Ledger::where('name', 'HDFC Current')->sole()->ifsc);
        $this->assertNull(Ledger::where('name', 'Electricity')->sole()->bank_name);
    }

    public function test_opening_balance_counts_on_the_right_side(): void
    {
        $this->assertSame(1000.0, $this->balance($this->ledger('bank', 'A', ['opening_balance' => 1000, 'opening_type' => 'dr'])));
        $this->assertSame(-2500.5, $this->balance($this->ledger('capital', 'B', ['opening_balance' => 2500.5, 'opening_type' => 'cr'])));
    }

    public function test_ledger_with_vouchers_cannot_be_deleted_but_can_go_inactive(): void
    {
        $this->actingAs($this->superAdmin());
        $used = $this->ledger('bank', 'Used Bank');
        $this->voucher('contra', ['main_ledger_id' => $used->id, 'counter_ledger_id' => Ledger::system('cash')->id, 'amount' => 10]);
        $free = $this->ledger('bank', 'Free Bank');

        $this->delete("/accounts/ledgers/{$used->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('ledgers', ['id' => $used->id]);

        $this->delete("/accounts/ledgers/{$free->id}")->assertRedirect('/accounts/ledgers');
        $this->assertDatabaseMissing('ledgers', ['id' => $free->id]);

        $this->put("/accounts/ledgers/{$used->id}", ['name' => 'Used Bank', 'ledger_group' => 'bank', 'opening_type' => 'dr', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->get('/accounts/ledgers')->assertDontSee('Used Bank');
        $this->get('/accounts/ledgers?status=inactive')->assertSee('Used Bank');
    }

    public function test_built_in_ledger_keeps_its_name_type_and_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash');

        $this->delete("/accounts/ledgers/{$cash->id}")->assertSessionHas('error');
        $this->put("/accounts/ledgers/{$cash->id}", ['name' => 'Hacked', 'ledger_group' => 'capital', 'opening_balance' => 5000, 'opening_type' => 'dr', 'status' => 'inactive'])->assertSessionHasNoErrors();

        $cash->refresh();
        $this->assertSame('Cash in Hand', $cash->name);
        $this->assertSame('cash', $cash->ledger_group);
        $this->assertSame('active', $cash->status);
        $this->assertSame('5000.00', $cash->opening_balance, 'The opening cash balance can still be entered');
    }

    // ------------------------------------------------------------- Customer link

    public function test_a_customer_gets_a_debtor_ledger_that_follows_their_details(): void
    {
        $customer = Customer::create(['company_name' => 'Asha Plastics', 'phone' => '9000000001', 'gstin' => '27ABCDE1234F1Z5', 'status' => 'active']);

        $ledger = $customer->ledger;
        $this->assertSame('Asha Plastics', $ledger->name);
        $this->assertSame('debtors', $ledger->ledger_group);
        $this->assertSame('27ABCDE1234F1Z5', $ledger->gstin);

        $customer->update(['company_name' => 'Asha Polymers', 'phone' => '9000000002']);
        $ledger->refresh();
        $this->assertSame('Asha Polymers', $ledger->name);
        $this->assertSame('9000000002', $ledger->phone);
        $this->assertSame(1, Ledger::where('customer_id', $customer->id)->count());
    }

    public function test_two_customers_with_the_same_name_get_separate_ledgers(): void
    {
        $first = Customer::create(['company_name' => 'Same Name', 'phone' => '9000000001', 'status' => 'active']);
        $second = Customer::create(['company_name' => 'Same Name', 'phone' => '9000000002', 'status' => 'active']);

        $this->assertSame('Same Name', $first->ledger->name);
        $this->assertSame("Same Name ({$second->code})", $second->ledger->name);

        $second->save();
        $this->assertSame("Same Name ({$second->code})", $second->fresh()->ledger->name);
    }

    public function test_customer_ledger_edits_cannot_overwrite_what_the_customer_owns(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = Customer::create(['company_name' => 'Asha Plastics', 'phone' => '9000000001', 'status' => 'active']);
        $ledger = $customer->ledger;

        $this->delete("/accounts/ledgers/{$ledger->id}")->assertSessionHas('error');
        $this->get("/accounts/ledgers/{$ledger->id}/edit")->assertOk()->assertSee('This ledger belongs to a customer');
        $this->put("/accounts/ledgers/{$ledger->id}", ['name' => 'Other', 'ledger_group' => 'capital', 'phone' => '9111111111', 'opening_balance' => 750, 'opening_type' => 'dr', 'pan' => 'ABCDE1234F', 'status' => 'active'])->assertSessionHasNoErrors();

        $ledger->refresh();
        $this->assertSame('Asha Plastics', $ledger->name);
        $this->assertSame('debtors', $ledger->ledger_group);
        $this->assertSame('9000000001', $ledger->phone);
        $this->assertSame('750.00', $ledger->opening_balance);
        $this->assertSame('ABCDE1234F', $ledger->pan);
    }

    public function test_customer_with_entries_cannot_be_deleted_and_one_without_takes_its_ledger_along(): void
    {
        $this->actingAs($this->superAdmin());
        $withEntries = Customer::create(['company_name' => 'Busy', 'phone' => '9000000001', 'status' => 'active']);
        $this->voucher('receipt', ['main_ledger_id' => Ledger::system('cash')->id, 'counter_ledger_id' => $withEntries->ledger->id, 'amount' => 100]);
        $this->delete("/sales/retention/{$withEntries->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $withEntries->id]);

        $empty = Customer::create(['company_name' => 'Idle', 'phone' => '9000000002', 'status' => 'active']);
        $ledgerId = $empty->ledger->id;
        $this->delete("/sales/retention/{$empty->id}")->assertRedirect('/sales/retention');
        $this->assertDatabaseMissing('ledgers', ['id' => $ledgerId]);
    }

    // ------------------------------------------------------------- Vouchers

    public function test_receipt_debits_the_bank_and_credits_the_customer(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $bank = $this->ledger('bank', 'HDFC');
        $customer = $this->ledger('debtors', 'Asha');

        $voucher = $this->voucher('receipt', ['main_ledger_id' => $bank->id, 'counter_ledger_id' => $customer->id, 'amount' => 5000, 'reference' => 'UTR123', 'narration' => 'Against bill 12']);

        $this->assertSame('RCT-0001', $voucher->code);
        $this->assertSame([5000.0, 0.0], $this->lineFor($voucher, $bank));
        $this->assertSame([0.0, 5000.0], $this->lineFor($voucher, $customer));
        $this->assertSame('5000.00', $voucher->amount);
        $this->assertSame($admin->id, $voucher->created_by);
        $this->assertSame('Asha', $voucher->particulars());
    }

    public function test_payment_debits_the_payee_and_credits_the_money_account(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash');
        $electricity = $this->ledger('direct_expenses', 'Electricity');

        $voucher = $this->voucher('payment', ['main_ledger_id' => $cash->id, 'counter_ledger_id' => $electricity->id, 'amount' => 1200.50]);

        $this->assertSame('PAY-0001', $voucher->code);
        $this->assertSame([1200.5, 0.0], $this->lineFor($voucher, $electricity));
        $this->assertSame([0.0, 1200.5], $this->lineFor($voucher, $cash));
    }

    public function test_contra_moves_money_between_money_accounts_only(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash');
        $bank = $this->ledger('bank', 'HDFC');
        $expense = $this->ledger('indirect_expenses', 'Rent');

        $voucher = $this->voucher('contra', ['main_ledger_id' => $bank->id, 'counter_ledger_id' => $cash->id, 'amount' => 700]);
        $this->assertSame('CNT-0001', $voucher->code);
        $this->assertSame([700.0, 0.0], $this->lineFor($voucher, $bank));
        $this->assertSame([0.0, 700.0], $this->lineFor($voucher, $cash));

        $this->post('/accounts/vouchers', ['type' => 'contra', 'voucher_date' => '2026-11-01', 'main_ledger_id' => $bank->id, 'counter_ledger_id' => $expense->id, 'amount' => 1])->assertSessionHasErrors('counter_ledger_id');
    }

    public function test_sale_within_state_posts_cgst_and_sgst(): void
    {
        $this->actingAs($this->superAdmin());
        $customer = $this->ledger('debtors', 'Asha');
        $sales = Ledger::system('sales');

        $voucher = $this->voucher('sales', ['main_ledger_id' => $customer->id, 'counter_ledger_id' => $sales->id, 'taxable_amount' => 10000, 'gst_rate' => 18, 'is_interstate' => 0, 'reference' => 'INV-1']);

        $this->assertSame('SAL-0001', $voucher->code);
        $this->assertSame('11800.00', $voucher->amount);
        $this->assertSame([11800.0, 0.0], $this->lineFor($voucher, $customer));
        $this->assertSame([0.0, 10000.0], $this->lineFor($voucher, $sales));
        $this->assertSame([0.0, 900.0], $this->lineFor($voucher, Ledger::system('output_cgst')));
        $this->assertSame([0.0, 900.0], $this->lineFor($voucher, Ledger::system('output_sgst')));
        $this->assertSame(0, $voucher->lines()->where('ledger_id', Ledger::system('output_igst')->id)->count());
        $this->assertSame('Asha', $voucher->particulars());
    }

    public function test_purchase_from_another_state_posts_input_igst(): void
    {
        $this->actingAs($this->superAdmin());
        $supplier = $this->ledger('creditors', 'ABC Resins');
        $purchases = Ledger::system('purchase');

        $voucher = $this->voucher('purchase', ['main_ledger_id' => $supplier->id, 'counter_ledger_id' => $purchases->id, 'taxable_amount' => 50000, 'gst_rate' => 18, 'is_interstate' => 1]);

        $this->assertSame('PUR-0001', $voucher->code);
        $this->assertSame([0.0, 59000.0], $this->lineFor($voucher, $supplier));
        $this->assertSame([50000.0, 0.0], $this->lineFor($voucher, $purchases));
        $this->assertSame([9000.0, 0.0], $this->lineFor($voucher, Ledger::system('input_igst')));
    }

    public function test_journal_records_free_balanced_lines(): void
    {
        $this->actingAs($this->superAdmin());
        $salary = $this->ledger('indirect_expenses', 'Salary');
        $pf = $this->ledger('current_liabilities', 'PF Payable');
        $payable = $this->ledger('current_liabilities', 'Salary Payable');

        $voucher = $this->voucher('journal', ['lines' => [
            ['ledger_id' => $salary->id, 'debit' => 100000],
            ['ledger_id' => $pf->id, 'credit' => 12000],
            ['ledger_id' => $payable->id, 'credit' => 88000],
        ], 'narration' => 'October salary']);

        $this->assertSame('JRN-0001', $voucher->code);
        $this->assertSame('100000.00', $voucher->amount);
        $this->assertCount(3, $voucher->lines);
        $this->assertNull($voucher->main_ledger_id);
    }

    public function test_journal_must_balance_and_each_line_is_one_sided(): void
    {
        $this->actingAs($this->superAdmin());
        $a = $this->ledger('indirect_expenses', 'A')->id;
        $b = $this->ledger('current_liabilities', 'B')->id;
        $post = fn (array $lines) => $this->post('/accounts/vouchers', ['type' => 'journal', 'voucher_date' => '2026-11-01', 'lines' => $lines]);

        $post([['ledger_id' => $a, 'debit' => 100], ['ledger_id' => $b, 'credit' => 99.99]])->assertSessionHasErrors('lines');
        $post([['ledger_id' => $a, 'debit' => 100, 'credit' => 100], ['ledger_id' => $b, 'credit' => 100]])->assertSessionHasErrors('lines.0.debit');
        $post([['ledger_id' => $a], ['ledger_id' => $b, 'credit' => 100]])->assertSessionHasErrors('lines.0.debit');
        $post([['ledger_id' => $a, 'debit' => 100]])->assertSessionHasErrors('lines');
        $post([['ledger_id' => $a, 'debit' => 100], ['ledger_id' => $a, 'credit' => 100]])->assertSessionHasErrors('lines');
        $post([['ledger_id' => 99999, 'debit' => 100], ['ledger_id' => $b, 'credit' => 100]])->assertSessionHasErrors('lines.0.ledger_id');
        $post([['debit' => 100], ['ledger_id' => $b, 'credit' => 100]])->assertSessionHasErrors('lines.0.ledger_id');
        $this->assertSame(0, Voucher::count());
    }

    public function test_voucher_ledgers_must_fit_the_voucher_type(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash')->id;
        $sales = Ledger::system('sales')->id;
        $customer = $this->ledger('debtors', 'Asha')->id;
        $supplier = $this->ledger('creditors', 'ABC')->id;
        $base = ['voucher_date' => '2026-11-01', 'amount' => 100];
        $gst = ['voucher_date' => '2026-11-01', 'taxable_amount' => 100, 'gst_rate' => 18, 'is_interstate' => 0];

        $this->post('/accounts/vouchers', $base + ['type' => 'receipt', 'main_ledger_id' => $customer, 'counter_ledger_id' => $supplier])->assertSessionHasErrors('main_ledger_id');
        $this->post('/accounts/vouchers', $base + ['type' => 'receipt', 'main_ledger_id' => $cash, 'counter_ledger_id' => $cash])->assertSessionHasErrors('counter_ledger_id');
        $this->post('/accounts/vouchers', $gst + ['type' => 'sales', 'main_ledger_id' => $supplier, 'counter_ledger_id' => $sales])->assertSessionHasErrors('main_ledger_id');
        $this->post('/accounts/vouchers', $gst + ['type' => 'sales', 'main_ledger_id' => $customer, 'counter_ledger_id' => $supplier])->assertSessionHasErrors('counter_ledger_id');
        $this->post('/accounts/vouchers', $gst + ['type' => 'purchase', 'main_ledger_id' => $customer, 'counter_ledger_id' => Ledger::system('purchase')->id])->assertSessionHasErrors('main_ledger_id');
        $this->assertSame(0, Voucher::count());
    }

    public function test_voucher_field_validation(): void
    {
        $this->actingAs($this->superAdmin());
        $base = ['main_ledger_id' => Ledger::system('cash')->id, 'counter_ledger_id' => $this->ledger('debtors', 'Asha')->id];

        $this->post('/accounts/vouchers', $base + ['voucher_date' => '2026-11-01', 'amount' => 1])->assertSessionHasErrors('type');
        $this->post('/accounts/vouchers', $base + ['type' => 'bribe', 'voucher_date' => '2026-11-01', 'amount' => 1])->assertSessionHasErrors('type');
        $this->post('/accounts/vouchers', $base + ['type' => 'receipt', 'amount' => 1])->assertSessionHasErrors('voucher_date');
        $this->post('/accounts/vouchers', $base + ['type' => 'receipt', 'voucher_date' => '2026-11-01', 'amount' => 0])->assertSessionHasErrors('amount');
        $this->post('/accounts/vouchers', $base + ['type' => 'receipt', 'voucher_date' => '2026-11-01', 'amount' => -1])->assertSessionHasErrors('amount');
        $this->post('/accounts/vouchers', $base + ['type' => 'sales', 'voucher_date' => '2026-11-01', 'taxable_amount' => 1, 'gst_rate' => 7, 'is_interstate' => 0])->assertSessionHasErrors();
        $this->get('/accounts/vouchers/create?type=bribe')->assertNotFound();
        $this->assertSame(0, Voucher::count());
    }

    public function test_an_unbalanced_voucher_can_never_be_posted(): void
    {
        $voucher = new Voucher(['voucher_date' => '2026-11-01']);
        $voucher->type = 'journal';

        $this->expectException(InvalidArgumentException::class);
        $voucher->post([['ledger_id' => Ledger::system('cash')->id, 'debit' => 100, 'credit' => 0], ['ledger_id' => Ledger::system('sales')->id, 'debit' => 0, 'credit' => 99]]);
    }

    public function test_inactive_ledger_cannot_be_newly_used_but_stays_editable_on_old_vouchers(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash');
        $customer = $this->ledger('debtors', 'Asha');
        $voucher = $this->voucher('receipt', ['main_ledger_id' => $cash->id, 'counter_ledger_id' => $customer->id, 'amount' => 100]);
        $customer->update(['status' => 'inactive']);

        $this->post('/accounts/vouchers', ['type' => 'receipt', 'voucher_date' => '2026-11-01', 'main_ledger_id' => $cash->id, 'counter_ledger_id' => $customer->id, 'amount' => 1])->assertSessionHasErrors('counter_ledger_id');
        $this->get('/dashboard'); // clears the error message naming the ledger
        $this->get('/accounts/vouchers/create?type=receipt')->assertDontSee('Asha');

        $this->get("/accounts/vouchers/{$voucher->id}/edit")->assertOk()->assertSee('Asha');
        $this->put("/accounts/vouchers/{$voucher->id}", ['voucher_date' => '2026-11-01', 'main_ledger_id' => $cash->id, 'counter_ledger_id' => $customer->id, 'amount' => 200])->assertSessionHasNoErrors();
        $this->assertSame('200.00', $voucher->fresh()->amount);
    }

    public function test_editing_replaces_the_lines_and_keeps_the_number(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash');
        $asha = $this->ledger('debtors', 'Asha');
        $ravi = $this->ledger('debtors', 'Ravi');
        $voucher = $this->voucher('receipt', ['main_ledger_id' => $cash->id, 'counter_ledger_id' => $asha->id, 'amount' => 100]);

        $this->get("/accounts/vouchers/{$voucher->id}/edit")->assertOk()->assertSee('value="100"', false);
        $this->put("/accounts/vouchers/{$voucher->id}", ['voucher_date' => '2026-11-02', 'main_ledger_id' => $cash->id, 'counter_ledger_id' => $ravi->id, 'amount' => 250, 'type' => 'payment'])->assertSessionHasNoErrors();

        $voucher->refresh();
        $this->assertSame('RCT-0001', $voucher->code);
        $this->assertSame('receipt', $voucher->type, 'The type of a saved voucher cannot be changed');
        $this->assertCount(2, $voucher->lines);
        $this->assertSame(0.0, $this->balance($asha));
        $this->assertSame(-250.0, $this->balance($ravi));
        $this->assertSame(250.0, $this->balance($cash));
    }

    public function test_a_failed_edit_leaves_the_voucher_untouched(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash');
        $asha = $this->ledger('debtors', 'Asha');
        $voucher = $this->voucher('receipt', ['main_ledger_id' => $cash->id, 'counter_ledger_id' => $asha->id, 'amount' => 100]);

        $this->put("/accounts/vouchers/{$voucher->id}", ['voucher_date' => '2026-12-25', 'main_ledger_id' => $cash->id, 'counter_ledger_id' => $asha->id, 'amount' => 0])->assertSessionHasErrors('amount');

        $voucher->refresh();
        $this->assertSame('2026-11-01', $voucher->voucher_date->toDateString());
        $this->assertSame(-100.0, $this->balance($asha));
    }

    public function test_journal_edit_form_round_trips(): void
    {
        $this->actingAs($this->superAdmin());
        $a = $this->ledger('indirect_expenses', 'A')->id;
        $b = $this->ledger('current_liabilities', 'B')->id;
        $voucher = $this->voucher('journal', ['lines' => [['ledger_id' => $a, 'debit' => 100], ['ledger_id' => $b, 'credit' => 100]]]);

        $rows = $this->get("/accounts/vouchers/{$voucher->id}/edit")->assertOk()->viewData('rows');

        $this->put("/accounts/vouchers/{$voucher->id}", ['voucher_date' => '2026-11-01', 'lines' => $rows])->assertSessionHasNoErrors();
        $this->assertCount(2, $voucher->fresh()->lines);
        $this->assertSame('100.00', $voucher->fresh()->amount);
    }

    public function test_deleting_a_voucher_reverses_its_effect_on_balances(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash');
        $voucher = $this->voucher('receipt', ['main_ledger_id' => $cash->id, 'counter_ledger_id' => $this->ledger('debtors', 'Asha')->id, 'amount' => 100]);
        $this->assertSame(100.0, $this->balance($cash));

        $this->delete("/accounts/vouchers/{$voucher->id}")->assertRedirect('/accounts/vouchers');

        $this->assertSame(0.0, $this->balance($cash));
        $this->assertDatabaseCount('voucher_lines', 0);
    }

    public function test_numbers_run_separately_per_type_and_do_not_repeat_after_a_delete(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash')->id;
        $asha = $this->ledger('debtors', 'Asha')->id;
        $receipt = fn () => $this->voucher('receipt', ['main_ledger_id' => $cash, 'counter_ledger_id' => $asha, 'amount' => 1]);

        $this->assertSame('RCT-0001', $receipt()->code);
        $second = $receipt();
        $this->assertSame('RCT-0002', $second->code);
        $this->assertSame('PAY-0001', $this->voucher('payment', ['main_ledger_id' => $cash, 'counter_ledger_id' => $asha, 'amount' => 1])->code);

        $receipt()->delete();
        $this->assertSame('RCT-0003', Voucher::nextCode('receipt'));
    }

    public function test_voucher_list_filters(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash')->id;
        $asha = $this->ledger('debtors', 'Asha')->id;
        $ravi = $this->ledger('debtors', 'Ravi')->id;
        $this->voucher('receipt', ['main_ledger_id' => $cash, 'counter_ledger_id' => $asha, 'amount' => 100, 'voucher_date' => '2026-10-01', 'reference' => 'UTR-A']);
        $this->voucher('payment', ['main_ledger_id' => $cash, 'counter_ledger_id' => $ravi, 'amount' => 50, 'voucher_date' => '2026-11-01', 'narration' => 'Refund']);

        $count = fn (string $query) => $this->get("/accounts/vouchers?$query")->assertOk()->viewData('vouchers')->total();

        $this->assertSame(2, $count(''));
        $this->assertSame(1, $count('type=payment'));
        $this->assertSame(1, $count("ledger_id=$asha"));
        $this->assertSame(2, $count("ledger_id=$cash"));
        $this->assertSame(1, $count('from=2026-10-15'));
        $this->assertSame(1, $count('to=2026-10-15'));
        $this->assertSame(1, $count('search=UTR-A'));
        $this->assertSame(1, $count('search=Refund'));
        $this->assertSame(50.0, $this->get('/accounts/vouchers?type=payment')->viewData('total'));
    }

    // ------------------------------------------------------------- Statement

    public function test_statement_runs_from_the_balance_brought_forward(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash')->id;
        $asha = $this->ledger('debtors', 'Asha', ['opening_balance' => 1000, 'opening_type' => 'dr']);
        $this->voucher('sales', ['main_ledger_id' => $asha->id, 'counter_ledger_id' => Ledger::system('sales')->id, 'taxable_amount' => 500, 'gst_rate' => 0, 'is_interstate' => 0, 'voucher_date' => '2026-09-15']);
        $this->voucher('sales', ['main_ledger_id' => $asha->id, 'counter_ledger_id' => Ledger::system('sales')->id, 'taxable_amount' => 2000, 'gst_rate' => 18, 'is_interstate' => 0, 'voucher_date' => '2026-10-05', 'narration' => 'Bottles']);
        $this->voucher('receipt', ['main_ledger_id' => $cash, 'counter_ledger_id' => $asha->id, 'amount' => 1000, 'voucher_date' => '2026-10-20']);
        $this->voucher('receipt', ['main_ledger_id' => $cash, 'counter_ledger_id' => $asha->id, 'amount' => 300, 'voucher_date' => '2026-11-20']);

        $response = $this->get("/accounts/ledgers/{$asha->id}?from=2026-10-01&to=2026-10-31")->assertOk();

        $this->assertSame(1500.0, $response->viewData('opening'));
        $this->assertSame([3860.0, 2860.0], $response->viewData('rows')->pluck('balance')->all());
        $this->assertSame(2860.0, $response->viewData('closing'));
        $this->assertSame(2360.0, $response->viewData('totalDebit'));
        $this->assertSame(1000.0, $response->viewData('totalCredit'));
        $response->assertSee('2,860.00 Dr')->assertSee('Bottles')->assertSee('Cash in Hand');

        $this->get("/accounts/ledgers/{$asha->id}?from=2026-11-01&to=2026-10-01")->assertSessionHasErrors('to');
    }

    public function test_statement_defaults_to_the_current_financial_year(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash');

        $this->travelTo('2026-11-07 12:00');
        $this->assertSame('2026-04-01', $this->get("/accounts/ledgers/{$cash->id}")->viewData('from')->toDateString());

        $this->travelTo('2027-02-10 12:00');
        $this->assertSame('2026-04-01', $this->get("/accounts/ledgers/{$cash->id}")->viewData('from')->toDateString());

        $this->travelTo('2027-04-01 12:00');
        $this->assertSame('2027-04-01', $this->get("/accounts/ledgers/{$cash->id}")->viewData('from')->toDateString());

        $this->travelBack();
    }

    public function test_ledger_list_shows_balances_and_the_money_summary(): void
    {
        $this->actingAs($this->superAdmin());
        $bank = $this->ledger('bank', 'HDFC', ['opening_balance' => 10000]);
        $asha = $this->ledger('debtors', 'Asha');
        $abc = $this->ledger('creditors', 'ABC', ['opening_balance' => 4000, 'opening_type' => 'cr']);
        $this->voucher('sales', ['main_ledger_id' => $asha->id, 'counter_ledger_id' => Ledger::system('sales')->id, 'taxable_amount' => 1000, 'gst_rate' => 18, 'is_interstate' => 0]);
        $this->voucher('payment', ['main_ledger_id' => $bank->id, 'counter_ledger_id' => $abc->id, 'amount' => 1500]);

        $response = $this->get('/accounts/ledgers')->assertOk();

        $this->assertSame(['money' => 8500.0, 'receivable' => 1180.0, 'payable' => 2500.0], $response->viewData('stats'));
        $response->assertSee('8,500.00 Dr')->assertSee('1,180.00 Dr')->assertSee('2,500.00 Cr');
    }

    // ------------------------------------------------------------- Trial balance

    public function test_trial_balance_agrees_after_every_voucher_type(): void
    {
        $this->actingAs($this->superAdmin());
        $cash = Ledger::system('cash');
        $bank = $this->ledger('bank', 'HDFC', ['opening_balance' => 100000, 'opening_type' => 'dr']);
        $capital = $this->ledger('capital', 'Capital', ['opening_balance' => 100000, 'opening_type' => 'cr']);
        $asha = $this->ledger('debtors', 'Asha');
        $abc = $this->ledger('creditors', 'ABC');
        $rent = $this->ledger('indirect_expenses', 'Rent');
        $machine = $this->ledger('fixed_assets', 'Blow Machine');

        $this->voucher('sales', ['main_ledger_id' => $asha->id, 'counter_ledger_id' => Ledger::system('sales')->id, 'taxable_amount' => 33333.33, 'gst_rate' => 18, 'is_interstate' => 0]);
        $this->voucher('purchase', ['main_ledger_id' => $abc->id, 'counter_ledger_id' => Ledger::system('purchase')->id, 'taxable_amount' => 12345.67, 'gst_rate' => 12, 'is_interstate' => 1]);
        $this->voucher('purchase', ['main_ledger_id' => $bank->id, 'counter_ledger_id' => $machine->id, 'taxable_amount' => 25000, 'gst_rate' => 18, 'is_interstate' => 0]);
        $this->voucher('receipt', ['main_ledger_id' => $bank->id, 'counter_ledger_id' => $asha->id, 'amount' => 10000]);
        $this->voucher('payment', ['main_ledger_id' => $cash->id, 'counter_ledger_id' => $rent->id, 'amount' => 777.77]);
        $this->voucher('contra', ['main_ledger_id' => $cash->id, 'counter_ledger_id' => $bank->id, 'amount' => 5000]);
        $this->voucher('journal', ['lines' => [['ledger_id' => $rent->id, 'debit' => 100.10], ['ledger_id' => $abc->id, 'credit' => 100.10]]]);

        $response = $this->get('/accounts/trial-balance?as_of=2026-11-30')->assertOk();

        $this->assertSame(0.0, $response->viewData('difference'));
        $this->assertSame($response->viewData('debit'), $response->viewData('credit'));
        $response->assertDontSee('Debits and credits differ')->assertSee('Blow Machine')->assertSee('Output CGST');
    }

    public function test_trial_balance_is_as_of_the_chosen_date_and_skips_zero_balances(): void
    {
        $this->actingAs($this->superAdmin());
        $asha = $this->ledger('debtors', 'Asha');
        $this->ledger('debtors', 'Nothing Here');
        $this->voucher('receipt', ['main_ledger_id' => Ledger::system('cash')->id, 'counter_ledger_id' => $asha->id, 'amount' => 100, 'voucher_date' => '2026-10-01']);
        $this->voucher('receipt', ['main_ledger_id' => Ledger::system('cash')->id, 'counter_ledger_id' => $asha->id, 'amount' => 50, 'voucher_date' => '2026-11-01']);

        $this->assertSame(100.0, $this->get('/accounts/trial-balance?as_of=2026-10-31')->viewData('debit'));
        $this->assertSame(150.0, $this->get('/accounts/trial-balance?as_of=2026-11-30')->viewData('debit'));
        $this->assertSame(0.0, $this->get('/accounts/trial-balance?as_of=2026-09-01')->viewData('debit'));
        $this->get('/accounts/trial-balance?as_of=2026-11-30')->assertDontSee('Nothing Here');
        $this->get('/accounts/trial-balance?as_of=not-a-date')->assertSessionHasErrors('as_of');
    }

    public function test_trial_balance_flags_opening_balances_that_do_not_agree(): void
    {
        $this->actingAs($this->superAdmin());
        $this->ledger('bank', 'HDFC', ['opening_balance' => 1000, 'opening_type' => 'dr']);

        $response = $this->get('/accounts/trial-balance')->assertOk();

        $this->assertSame(1000.0, $response->viewData('difference'));
        $response->assertSee('Debits and credits differ')->assertSee('1,000.00');
    }

    // ------------------------------------------------------------- Pages

    public function test_every_page_renders(): void
    {
        $this->actingAs($this->superAdmin());
        $bank = $this->ledger('bank', 'HDFC', ['bank_name' => 'HDFC', 'account_number' => '123', 'ifsc' => 'HDFC0001234', 'remarks' => 'Main']);
        $party = $this->ledger('creditors', 'ABC', ['phone' => '9000000001', 'address' => 'Plot 1', 'gstin' => '27ABCDE1234F1Z5']);
        $customer = Customer::create(['company_name' => 'Asha', 'phone' => '9000000002', 'status' => 'active']);

        $vouchers = [
            $this->voucher('receipt', ['main_ledger_id' => $bank->id, 'counter_ledger_id' => $customer->ledger->id, 'amount' => 1]),
            $this->voucher('payment', ['main_ledger_id' => $bank->id, 'counter_ledger_id' => $party->id, 'amount' => 1, 'narration' => 'N', 'reference' => 'R']),
            $this->voucher('contra', ['main_ledger_id' => $bank->id, 'counter_ledger_id' => Ledger::system('cash')->id, 'amount' => 1]),
            $this->voucher('sales', ['main_ledger_id' => $customer->ledger->id, 'counter_ledger_id' => Ledger::system('sales')->id, 'taxable_amount' => 1, 'gst_rate' => 18, 'is_interstate' => 0]),
            $this->voucher('purchase', ['main_ledger_id' => $party->id, 'counter_ledger_id' => Ledger::system('purchase')->id, 'taxable_amount' => 1, 'gst_rate' => 18, 'is_interstate' => 1]),
            $this->voucher('journal', ['lines' => [['ledger_id' => $bank->id, 'debit' => 1], ['ledger_id' => $party->id, 'credit' => 1]]]),
        ];

        foreach (array_keys(Voucher::TYPES) as $type) {
            $this->get("/accounts/vouchers/create?type=$type")->assertOk();
        }
        foreach ($vouchers as $voucher) {
            $this->get("/accounts/vouchers/{$voucher->id}")->assertOk();
            $this->get("/accounts/vouchers/{$voucher->id}/edit")->assertOk();
        }
        foreach ([$bank, $party, $customer->ledger, Ledger::system('cash')] as $ledger) {
            $this->get("/accounts/ledgers/{$ledger->id}")->assertOk();
            $this->get("/accounts/ledgers/{$ledger->id}/edit")->assertOk();
        }
        foreach (['/accounts/ledgers', '/accounts/ledgers/create', '/accounts/ledgers/create?group=bank', '/accounts/vouchers', '/accounts/trial-balance'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
