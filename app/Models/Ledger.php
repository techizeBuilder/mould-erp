<?php

namespace App\Models;

use App\Models\Concerns\HasMasterCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** An account in the books. Balances are signed with debit positive. */
class Ledger extends Model
{
    use HasMasterCode;

    public const CODE_PREFIX = 'LED';

    public const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    public const NATURES = [
        'asset' => 'Assets',
        'liability' => 'Liabilities & Capital',
        'income' => 'Income',
        'expense' => 'Expenses',
    ];

    /** Every type of ledger, under its nature. */
    public const GROUPS = [
        'cash' => ['label' => 'Cash in Hand', 'nature' => 'asset'],
        'bank' => ['label' => 'Bank Accounts', 'nature' => 'asset'],
        'debtors' => ['label' => 'Sundry Debtors (Customers)', 'nature' => 'asset'],
        'stock' => ['label' => 'Stock in Hand', 'nature' => 'asset'],
        'fixed_assets' => ['label' => 'Fixed Assets (Machines, Moulds, Building)', 'nature' => 'asset'],
        'investments' => ['label' => 'Investments', 'nature' => 'asset'],
        'loans_advances' => ['label' => 'Loans & Advances Given', 'nature' => 'asset'],
        'deposits' => ['label' => 'Deposits', 'nature' => 'asset'],
        'current_assets' => ['label' => 'Other Current Assets', 'nature' => 'asset'],

        'capital' => ['label' => 'Capital Account', 'nature' => 'liability'],
        'reserves' => ['label' => 'Reserves & Surplus', 'nature' => 'liability'],
        'bank_od' => ['label' => 'Bank OD / Cash Credit', 'nature' => 'liability'],
        'secured_loans' => ['label' => 'Secured Loans', 'nature' => 'liability'],
        'unsecured_loans' => ['label' => 'Unsecured Loans', 'nature' => 'liability'],
        'creditors' => ['label' => 'Sundry Creditors (Suppliers)', 'nature' => 'liability'],
        'duties_taxes' => ['label' => 'Duties & Taxes (GST, TDS)', 'nature' => 'liability'],
        'provisions' => ['label' => 'Provisions', 'nature' => 'liability'],
        'current_liabilities' => ['label' => 'Other Current Liabilities', 'nature' => 'liability'],

        'sales' => ['label' => 'Sales Accounts', 'nature' => 'income'],
        'direct_income' => ['label' => 'Direct Income', 'nature' => 'income'],
        'indirect_income' => ['label' => 'Indirect Income', 'nature' => 'income'],

        'purchase' => ['label' => 'Purchase Accounts', 'nature' => 'expense'],
        'direct_expenses' => ['label' => 'Direct Expenses (Power, Wages, Freight In)', 'nature' => 'expense'],
        'indirect_expenses' => ['label' => 'Indirect Expenses (Salary, Rent, Office)', 'nature' => 'expense'],
    ];

    /** Money accounts: what receipts, payments and contra move money through. */
    public const CASH_GROUPS = ['cash', 'bank', 'bank_od'];

    public const BANK_GROUPS = ['bank', 'bank_od'];

    /** Groups whose ledgers are people or companies. */
    public const PARTY_GROUPS = ['debtors', 'creditors', 'secured_loans', 'unsecured_loans', 'loans_advances'];

    protected $fillable = [
        'name', 'ledger_group', 'opening_balance', 'opening_type', 'contact_person', 'phone', 'email', 'address', 'state',
        'gstin', 'pan', 'bank_name', 'account_number', 'ifsc', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VoucherLine::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function system(string $key): self
    {
        return self::where('system_key', $key)->firstOrFail();
    }

    /** Group labels under their nature, for a select with optgroups. */
    public static function groupOptions(): array
    {
        $options = [];
        foreach (self::GROUPS as $key => $group) {
            $options[self::NATURES[$group['nature']]][$key] = $group['label'];
        }

        return $options;
    }

    /** Creates or refreshes the debtor ledger that goes with a customer. */
    public static function syncCustomer(Customer $customer): self
    {
        $ledger = self::firstOrNew(['customer_id' => $customer->id]);
        $ledger->customer_id = $customer->id;

        $taken = self::where('name', $customer->company_name)->when($ledger->exists, fn ($q) => $q->whereKeyNot($ledger->id))->exists();
        $ledger->name = $taken ? "{$customer->company_name} ({$customer->code})" : $customer->company_name;
        $ledger->ledger_group = 'debtors';
        $ledger->fill($customer->only(['contact_person', 'phone', 'email', 'address', 'state', 'gstin']));
        $ledger->status ??= 'active';
        $ledger->created_by ??= $customer->created_by;
        $ledger->save();

        return $ledger;
    }

    /** Adds total_debit / total_credit, counting vouchers up to $upTo when given. */
    public function scopeWithTotals(Builder $query, Carbon|string|null $upTo = null): void
    {
        $dated = fn ($q) => $q->when($upTo, fn ($q) => $q->whereHas('voucher', fn ($v) => $v->whereDate('voucher_date', '<=', $upTo)));

        $query->withSum(['lines as total_debit' => $dated], 'debit')->withSum(['lines as total_credit' => $dated], 'credit');
    }

    public function signedOpening(): float
    {
        return round((float) $this->opening_balance * ($this->opening_type === 'cr' ? -1 : 1), 2);
    }

    /** Signed balance; needs scopeWithTotals. */
    public function closing(): float
    {
        return round($this->signedOpening() + (float) $this->total_debit - (float) $this->total_credit, 2);
    }

    /** Signed balance brought forward into $from: the opening plus everything dated before it. */
    public function balanceBefore(Carbon|string $from): float
    {
        $before = fn (string $column) => (float) $this->lines()->whereHas('voucher', fn ($v) => $v->whereDate('voucher_date', '<', $from))->sum($column);

        return round($this->signedOpening() + $before('debit') - $before('credit'), 2);
    }

    public function groupLabel(): string
    {
        return self::GROUPS[$this->ledger_group]['label'] ?? (string) $this->ledger_group;
    }

    public function isCustomerLedger(): bool
    {
        return (bool) $this->customer_id;
    }

    public function isSystem(): bool
    {
        return (bool) $this->system_key;
    }

    /** Vouchers post to the ledger's name and group by rule, so these can't change. */
    public function isLocked(): bool
    {
        return $this->isSystem() || $this->isCustomerLedger();
    }
}
