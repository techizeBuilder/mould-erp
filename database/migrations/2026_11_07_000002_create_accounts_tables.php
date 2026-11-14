<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts: ledgers of every type and double-entry vouchers. The single "accounts" permission
 * module is split into one per screen (existing rights are copied), the ledgers vouchers rely on
 * are created, and each existing customer gets their debtor ledger.
 */
return new class extends Migration
{
    protected const MODULES = ['accounts_ledgers', 'accounts_vouchers', 'accounts_trial_balance'];

    protected const SYSTEM_LEDGERS = [
        'cash' => ['Cash in Hand', 'cash'],
        'sales' => ['Sales', 'sales'],
        'purchase' => ['Purchases', 'purchase'],
        'output_cgst' => ['Output CGST', 'duties_taxes'],
        'output_sgst' => ['Output SGST', 'duties_taxes'],
        'output_igst' => ['Output IGST', 'duties_taxes'],
        'input_cgst' => ['Input CGST', 'duties_taxes'],
        'input_sgst' => ['Input SGST', 'duties_taxes'],
        'input_igst' => ['Input IGST', 'duties_taxes'],
    ];

    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->string('ledger_group', 30);
            $table->string('system_key', 30)->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->string('opening_type', 2)->default('dr');
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 1000)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 30)->nullable();
            $table->string('ifsc', 11)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('ledger_group');
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('type', 20);
            $table->date('voucher_date');
            $table->string('reference', 60)->nullable();
            $table->string('narration', 500)->nullable();
            // The two ledgers picked on the simple forms (none for a journal), kept so the form can reopen.
            $table->foreignId('main_ledger_id')->nullable()->constrained('ledgers')->restrictOnDelete();
            $table->foreignId('counter_ledger_id')->nullable()->constrained('ledgers')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('taxable_amount', 14, 2)->nullable();
            $table->decimal('gst_rate', 5, 2)->nullable();
            $table->boolean('is_interstate')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'voucher_date']);
            $table->index('voucher_date');
        });

        Schema::create('voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_id')->constrained()->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
        });

        $number = 0;
        $code = function () use (&$number) {
            return 'LED-'.str_pad((string) ++$number, 4, '0', STR_PAD_LEFT);
        };
        $now = now();

        foreach (self::SYSTEM_LEDGERS as $key => [$name, $group]) {
            DB::table('ledgers')->insert(['code' => $code(), 'name' => $name, 'ledger_group' => $group, 'system_key' => $key, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (DB::table('customers')->orderBy('id')->get() as $customer) {
            $taken = DB::table('ledgers')->where('name', $customer->company_name)->exists();
            DB::table('ledgers')->insert([
                'code' => $code(),
                'name' => $taken ? "{$customer->company_name} ({$customer->code})" : $customer->company_name,
                'ledger_group' => 'debtors',
                'customer_id' => $customer->id,
                'contact_person' => $customer->contact_person,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'state' => $customer->state,
                'gstin' => $customer->gstin,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('user_permissions')->where('module', 'accounts')->get() as $row) {
            foreach (self::MODULES as $module) {
                DB::table('user_permissions')->updateOrInsert(
                    ['user_id' => $row->user_id, 'module' => $module],
                    collect((array) $row)->only(['can_show', 'can_view', 'can_create', 'can_edit', 'can_delete', 'created_at', 'updated_at'])->all()
                );
            }
        }
        DB::table('user_permissions')->where('module', 'accounts')->delete();
    }

    public function down(): void
    {
        foreach (DB::table('user_permissions')->where('module', 'accounts_ledgers')->get() as $row) {
            DB::table('user_permissions')->insert(['module' => 'accounts'] + collect((array) $row)->except(['id', 'module'])->all());
        }
        DB::table('user_permissions')->whereIn('module', self::MODULES)->delete();

        Schema::dropIfExists('voucher_lines');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('ledgers');
    }
};
