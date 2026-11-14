<?php

namespace App\Models;

use App\Models\Concerns\HasMasterCode;
use App\Support\QuotationTotals;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A price offer to a lead or customer. Only a draft can be edited: once sent, changing
 * the offer means a new revision (QTN-0007-R1) so the buyer's copy stays on record.
 */
class Quotation extends Model
{
    use HasMasterCode;

    public const CODE_PREFIX = 'QTN';

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'negotiation' => 'In Negotiation',
        'won' => 'Won',
        'lost' => 'Lost',
        'revised' => 'Revised',
    ];

    public const OPEN_STATUSES = ['draft', 'sent', 'negotiation'];

    public const TRANSITIONS = [
        'draft' => ['sent', 'lost'],
        'sent' => ['negotiation', 'won', 'lost'],
        'negotiation' => ['sent', 'won', 'lost'],
    ];

    public const PRICE_BASES = [
        'ex_works' => 'Ex-works (our factory)',
        'for_destination' => 'FOR destination (freight included)',
        'to_pay' => 'Freight to pay',
    ];

    public const SAMPLE_STATUSES = [
        'not_required' => 'Not required',
        'pending' => 'To be sent',
        'sent' => 'Sent to buyer',
        'approved' => 'Approved by buyer',
        'rejected' => 'Rejected by buyer',
    ];

    public const DEFAULT_VALIDITY_DAYS = 15;

    protected $fillable = [
        'lead_id', 'customer_id', 'quote_date', 'valid_until', 'price_basis', 'payment_terms', 'delivery_days', 'mould_lead_days',
        'packing', 'sample_status', 'sample_sent_on', 'discount_percent', 'gst_percent', 'freight_amount', 'terms', 'remarks', 'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'sent_on' => 'date',
            'sample_sent_on' => 'date',
            'won_on' => 'date',
            'revision' => 'integer',
            'delivery_days' => 'integer',
            'mould_lead_days' => 'integer',
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

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(QuotationStatusLog::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    /** Every version of this quotation. */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'code', 'code')->orderBy('revision');
    }

    /** Leaves out versions replaced by a newer revision. */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', '!=', 'revised');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeExpired(Builder $query): void
    {
        $query->open()->whereDate('valid_until', '<', today());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 7): void
    {
        $query->open()->whereDate('valid_until', '>=', today())->whereDate('valid_until', '<=', today()->addDays($days));
    }

    public function number(): string
    {
        return $this->code.($this->revision ? "-R{$this->revision}" : '');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isExpired(): bool
    {
        return $this->isOpen() && $this->valid_until?->lt(today());
    }

    public function canRevise(): bool
    {
        return in_array($this->status, ['sent', 'negotiation', 'lost'], true);
    }

    public function canDelete(): bool
    {
        return $this->isDraft() && $this->revision === 0;
    }

    public function nextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function buyerName(): string
    {
        return $this->customer?->company_name ?? $this->lead?->company_name ?? '—';
    }

    public function buyerParty(): Customer|Lead|null
    {
        return $this->customer ?? $this->lead;
    }

    /** Rebuilds the stored totals from the lines. */
    public function recalculate(): static
    {
        $this->forceFill(QuotationTotals::compute(
            $this->items()->get()->map(fn (QuotationItem $i) => ['quantity' => $i->quantity, 'rate' => $i->rate, 'mould_charge' => $i->mould_charge]),
            $this->discount_percent,
            $this->freight_amount,
            $this->gst_percent,
        ))->save();

        return $this;
    }

    public function logStatus(?string $from, string $to, ?string $note, ?User $by): void
    {
        $this->statusLogs()->create(['from_status' => $from, 'to_status' => $to, 'note' => $note, 'user_id' => $by?->id]);
    }

    /**
     * Moves the quotation along. Winning it creates the customer (if the buyer was still a
     * lead) and puts each line in their order history for retention tracking.
     * $details: note; lost_reason + competitor when lost; po_number + won_on when won.
     *
     * @throws DomainException when the move isn't allowed from the current status
     */
    public function changeStatus(string $to, array $details, ?User $by): void
    {
        if (! in_array($to, $this->nextStatuses(), true)) {
            throw new DomainException('A quotation that is '.$this->statusLabel().' cannot be marked '.(self::STATUSES[$to] ?? $to).'.');
        }

        DB::transaction(function () use ($to, $details, $by) {
            $from = $this->status;
            $this->status = $to;

            if ($to === 'sent') {
                $this->sent_on ??= today();
            } elseif ($to === 'lost') {
                $this->lost_reason = $details['lost_reason'] ?? null;
                $this->competitor = $details['competitor'] ?? null;
            } elseif ($to === 'won') {
                $this->po_number = $details['po_number'] ?? null;
                $this->won_on = $details['won_on'] ?? today();
                $this->customer_id = $this->customer_id ?? $this->lead?->customer_id ?? Customer::fromLead($this->lead, $by)->id;
            }

            $this->save();
            $this->logStatus($from, $to, $details['note'] ?? null, $by);

            if ($to === 'won') {
                $this->createOrders($by);
            }

            $this->lead?->syncStatusFromQuotations();
        });
    }

    protected function createOrders(?User $by): void
    {
        $keep = 1 - (float) $this->discount_percent / 100;

        foreach ($this->items as $item) {
            $order = new CustomerOrder([
                'customer_id' => $this->customer_id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'order_date' => $this->won_on,
                'po_number' => $this->po_number,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'rate' => round((float) $item->rate * $keep, 3),
                'amount' => round((float) $item->amount * $keep, 2),
            ]);
            $order->quotation_id = $this->id;
            $order->created_by = $by?->id;
            $order->save();
        }
    }

    /** Starts the next version as a draft, replacing this one. */
    public function revise(?User $by): self
    {
        if (! $this->canRevise()) {
            throw new DomainException('A quotation that is '.$this->statusLabel().' cannot be revised.');
        }

        return DB::transaction(function () use ($by) {
            $validity = max(1, (int) $this->quote_date->diffInDays($this->valid_until));

            $next = $this->replicate(['sent_on', 'lost_reason', 'competitor', 'created_at', 'updated_at']);
            $next->fill(['quote_date' => today(), 'valid_until' => today()->addDays($validity)]);
            $next->status = 'draft';
            $next->revision = self::where('code', $this->code)->max('revision') + 1;
            $next->parent_id = $this->id;
            $next->created_by = $by?->id;
            $next->save();

            foreach ($this->items as $item) {
                $copy = $item->replicate(['quotation_id', 'created_at', 'updated_at']);
                $next->items()->save($copy);
            }
            $next->recalculate();
            $next->logStatus(null, 'draft', "Revision of {$this->number()}", $by);

            $from = $this->status;
            $this->status = 'revised';
            $this->save();
            $this->logStatus($from, 'revised', "Replaced by {$next->number()}", $by);

            $this->lead?->syncStatusFromQuotations();

            return $next;
        });
    }

    public function validityLabel(): string
    {
        if (! $this->isOpen()) {
            return $this->valid_until->format('d M Y');
        }
        $days = (int) today()->diffInDays($this->valid_until, false);

        return match (true) {
            $days < 0 => 'Expired '.abs($days).' d ago',
            $days === 0 => 'Expires today',
            default => "{$days} d left",
        };
    }

    public function sampleLabel(): string
    {
        return self::SAMPLE_STATUSES[$this->sample_status] ?? '—';
    }

    public static function sampleTone(?string $status): string
    {
        return match ($status) {
            'approved' => 'bg-emerald-50 text-emerald-700',
            'rejected' => 'bg-red-50 text-red-700',
            'sent', 'pending' => 'bg-amber-50 text-amber-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public function statusTone(): string
    {
        return self::tone($this->status);
    }

    public static function tone(?string $status): string
    {
        return match ($status) {
            'draft' => 'bg-slate-100 text-slate-600',
            'sent' => 'bg-sky-50 text-sky-700',
            'negotiation' => 'bg-amber-50 text-amber-700',
            'won' => 'bg-emerald-50 text-emerald-700',
            'lost' => 'bg-red-50 text-red-700',
            default => 'bg-slate-100 text-slate-500',
        };
    }
}
