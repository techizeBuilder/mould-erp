<?php

namespace App\Models;

use App\Support\VoucherLines;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** A double-entry transaction: its lines always balance. */
class Voucher extends Model
{
    /**
     * prefix: numbering; main / counter: the form's two ledger fields and which ledger groups each
     * accepts (null = any group other than a money account). Journal has free lines instead.
     */
    public const TYPES = [
        'receipt' => ['label' => 'Receipt', 'prefix' => 'RCT', 'hint' => 'Money received.', 'main' => 'Received into', 'counter' => 'Received from'],
        'payment' => ['label' => 'Payment', 'prefix' => 'PAY', 'hint' => 'Money paid: suppliers, expenses, salaries.', 'main' => 'Paid from', 'counter' => 'Paid to'],
        'contra' => ['label' => 'Contra', 'prefix' => 'CNT', 'hint' => 'Between cash and bank.', 'main' => 'Transfer to', 'counter' => 'Transfer from'],
        'sales' => ['label' => 'Sales', 'prefix' => 'SAL', 'hint' => 'A sale, with GST.', 'main' => 'Customer', 'counter' => 'Sales ledger'],
        'purchase' => ['label' => 'Purchase', 'prefix' => 'PUR', 'hint' => 'A purchase or bill, with GST.', 'main' => 'Supplier', 'counter' => 'Purchase / expense ledger'],
        'journal' => ['label' => 'Journal', 'prefix' => 'JRN', 'hint' => 'Adjustments that move no cash.'],
    ];

    public const GST_RATES = [0, 5, 12, 18, 28];

    protected $fillable = ['voucher_date', 'reference', 'narration', 'main_ledger_id', 'counter_ledger_id', 'taxable_amount', 'gst_rate', 'is_interstate'];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'is_interstate' => 'boolean',
        ];
    }

    /** Ledger groups a field may use for this voucher type. */
    public static function allowedGroups(string $type, string $field): array
    {
        $cash = Ledger::CASH_GROUPS;
        $other = array_values(array_diff(array_keys(Ledger::GROUPS), $cash));

        return match ("$type.$field") {
            'receipt.main', 'payment.main', 'contra.main', 'contra.counter' => $cash,
            'receipt.counter', 'payment.counter' => $other,
            'sales.main' => ['debtors', ...$cash],
            'sales.counter' => ['sales', 'direct_income', 'indirect_income'],
            'purchase.main' => ['creditors', ...$cash],
            'purchase.counter' => ['purchase', 'direct_expenses', 'indirect_expenses', 'fixed_assets', 'stock'],
            default => array_keys(Ledger::GROUPS),
        };
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VoucherLine::class);
    }

    public function mainLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'main_ledger_id');
    }

    public function counterLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'counter_ledger_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function nextCode(string $type): string
    {
        $prefix = self::TYPES[$type]['prefix'].'-';
        $number = 1;

        $last = self::where('code', 'like', $prefix.'%')->orderByDesc('id')->value('code');
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $number = (int) $m[1] + 1;
        }

        do {
            $code = $prefix.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /** Saves the voucher and replaces its lines together, refusing anything that doesn't balance. */
    public function post(array $lines): static
    {
        if (! VoucherLines::isBalanced($lines)) {
            throw new InvalidArgumentException('A voucher must have equal debits and credits.');
        }

        return DB::transaction(function () use ($lines) {
            $this->code ??= self::nextCode($this->type);
            $this->amount = VoucherLines::total($lines, 'debit');
            $this->save();
            $this->lines()->delete();
            $this->lines()->createMany($lines);

            return $this;
        });
    }

    /** Sales, purchase and journal have no single counterparty field worth a blank in the list. */
    public function particulars(): string
    {
        if ($this->type === 'journal') {
            return $this->lines->map(fn (VoucherLine $l) => $l->ledger->name)->unique()->take(3)->join(', ');
        }

        return $this->partyLedger()?->name ?? '—';
    }

    /** The ledger a reader thinks of as "who this was with". */
    public function partyLedger(): ?Ledger
    {
        return in_array($this->type, ['sales', 'purchase'], true) ? $this->mainLedger : $this->counterLedger;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? (string) $this->type;
    }

    public function typeTone(): string
    {
        return match ($this->type) {
            'receipt', 'sales' => 'bg-emerald-50 text-emerald-700',
            'payment', 'purchase' => 'bg-amber-50 text-amber-700',
            'contra' => 'bg-sky-50 text-sky-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
