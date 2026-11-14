<?php

namespace App\Models;

use App\Models\Concerns\HasMasterCode;
use App\Support\CreditCheck;
use App\Support\Money;
use App\Support\QuotationTotals;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A customer's confirmed order: draft → pending approval → approved or rejected.
 * It can be changed only while draft or rejected, so what was approved is what stays on record.
 * Approval puts its products in the customer's order history.
 */
class SalesOrder extends Model
{
    use HasMasterCode;

    public const CODE_PREFIX = 'SO';

    public const STATUSES = [
        'draft' => 'Draft',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    public const TRANSITIONS = [
        'draft' => ['pending', 'cancelled'],
        'pending' => ['approved', 'rejected', 'cancelled'],
        'rejected' => ['pending', 'cancelled'],
        'approved' => ['cancelled'],
    ];

    protected $fillable = [
        'customer_id', 'quotation_id', 'order_date', 'required_by', 'po_number', 'po_date', 'shipping_address', 'price_basis',
        'payment_terms', 'packing', 'discount_percent', 'gst_percent', 'freight_amount', 'remarks', 'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'required_by' => 'date',
            'po_date' => 'date',
            'decided_at' => 'datetime',
            'discount_percent' => 'decimal:2',
            'gst_percent' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'mould_total' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SalesOrderLog::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Everything other than a cancelled order counts as a real order. */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', '!=', 'cancelled');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canDelete(): bool
    {
        return $this->status === 'draft';
    }

    public function canCancel(): bool
    {
        return in_array('cancelled', self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Nobody approves their own order, except the super admin, who may be the only approver. */
    public function canBeDecidedBy(User $user): bool
    {
        return $user->isSuperAdmin() || (int) $this->created_by !== (int) $user->id;
    }

    /** Rebuilds the stored totals from the lines. */
    public function recalculate(): static
    {
        $this->forceFill(QuotationTotals::compute(
            $this->items()->get()->map(fn (SalesOrderItem $i) => ['quantity' => $i->quantity, 'rate' => $i->rate, 'mould_charge' => $i->mould_charge]),
            $this->discount_percent,
            $this->freight_amount,
            $this->gst_percent,
        ))->save();

        return $this;
    }

    public function creditCheck(): array
    {
        return CreditCheck::evaluate($this->customer?->credit_limit, $this->customer?->ledgerBalance(), $this->total);
    }

    public function exceedsCredit(): bool
    {
        return $this->creditCheck()['exceeded'];
    }

    /**
     * What an approver should look at. Each is [level, message], level being ok, warning or danger.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function approvalChecks(): array
    {
        $checks = [];
        $credit = $this->creditCheck();
        $balance = (float) $this->customer?->ledgerBalance();
        $owing = 'Ledger balance ₹'.Money::format($balance).' + this order ₹'.Money::format($this->total).' = ₹'.Money::format($credit['exposure']);

        $checks[] = match (true) {
            ! $credit['limited'] => ['warning', "No credit limit is set for this customer. $owing."],
            $credit['exceeded'] => ['danger', "Over the credit limit of ₹".Money::format($this->customer->credit_limit).' by ₹'.Money::format($credit['over_by']).". $owing."],
            default => ['ok', 'Within the credit limit of ₹'.Money::format($this->customer->credit_limit).". $owing."],
        };

        if ($this->customer?->status !== 'active') {
            $checks[] = ['warning', 'This customer is marked Inactive.'];
        }

        foreach ($this->items as $item) {
            $product = $item->product;

            if (! $product) {
                $checks[] = ['warning', "{$item->product_code} has since been deleted from Product Master."];
            } elseif ($product->status !== 'active') {
                $checks[] = ['warning', "{$item->product_code} is marked Inactive in Product Master."];
            } elseif ($product->isManufactured() && ! $product->mould_id && $product->business_type === 'manufacturing') {
                $checks[] = ['warning', "{$item->product_code} has no mould linked, so it can't be made yet."];
            }

            if ((float) $item->rate == 0) {
                $checks[] = ['warning', "{$item->product_code} is priced at zero."];
            }
        }

        if ($this->required_by?->lt(today())) {
            $checks[] = ['warning', 'The required-by date has already passed.'];
        }

        return $checks;
    }

    /** @throws DomainException when the move isn't allowed from the current status */
    protected function moveTo(string $to, ?string $note, ?User $by): void
    {
        if (! in_array($to, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw new DomainException('An order that is '.$this->statusLabel().' cannot become '.self::STATUSES[$to].'.');
        }

        $from = $this->status;
        $this->status = $to;
        $this->save();
        $this->logs()->create(['from_status' => $from, 'to_status' => $to, 'note' => $note, 'user_id' => $by?->id]);
    }

    public function submit(?User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->forceFill(['decision_note' => null, 'decided_by' => null, 'decided_at' => null]);
            $this->moveTo('pending', null, $by);
        });
    }

    public function approve(?User $by, ?string $note = null): void
    {
        DB::transaction(function () use ($by, $note) {
            $this->forceFill(['decision_note' => $note, 'decided_by' => $by?->id, 'decided_at' => now()]);
            $this->moveTo('approved', $note, $by);
            $this->recordInOrderHistory($by);
        });
    }

    public function reject(?User $by, string $reason): void
    {
        DB::transaction(function () use ($by, $reason) {
            $this->forceFill(['decision_note' => $reason, 'decided_by' => $by?->id, 'decided_at' => now()]);
            $this->moveTo('rejected', $reason, $by);
        });
    }

    /** A cancelled order is no longer business done, so it leaves the customer's order history. */
    public function cancel(?User $by, string $reason): void
    {
        DB::transaction(function () use ($by, $reason) {
            $this->moveTo('cancelled', $reason, $by);
            CustomerOrder::where('sales_order_id', $this->id)->delete();
        });
    }

    /**
     * The approved order is the record of the sale. Winning its quotation already logged estimated
     * lines, so those are replaced rather than counted twice.
     */
    protected function recordInOrderHistory(?User $by): void
    {
        if ($this->quotation_id) {
            CustomerOrder::where('quotation_id', $this->quotation_id)->whereNull('sales_order_id')->delete();
        }

        $keep = 1 - (float) $this->discount_percent / 100;

        foreach ($this->items as $item) {
            $order = new CustomerOrder([
                'customer_id' => $this->customer_id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'order_date' => $this->order_date,
                'po_number' => $this->po_number,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'rate' => round((float) $item->rate * $keep, 3),
                'amount' => round((float) $item->amount * $keep, 2),
            ]);
            $order->quotation_id = $this->quotation_id;
            $order->sales_order_id = $this->id;
            $order->created_by = $by?->id;
            $order->save();
        }
    }

    public function statusTone(): string
    {
        return self::tone($this->status);
    }

    public static function tone(?string $status): string
    {
        return match ($status) {
            'pending' => 'bg-amber-50 text-amber-700',
            'approved' => 'bg-emerald-50 text-emerald-700',
            'rejected' => 'bg-red-50 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
