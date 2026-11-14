<?php

namespace App\Models;

use App\Models\Concerns\HasMasterCode;
use App\Support\BuyerScore;
use App\Support\RetentionHealth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/** A buyer who has ordered from us, or was added directly as an existing client. */
class Customer extends Model
{
    use HasMasterCode;

    public const CODE_PREFIX = 'CUS';

    public const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    protected $fillable = [
        'company_name', 'contact_person', 'phone', 'email', 'city', 'state', 'address', 'shipping_address', 'gstin', 'pan', 'industry',
        'buyer_type', 'payment_terms', 'credit_limit', 'credit_days', 'source', 'reorder_cycle_days', 'assigned_to', 'status', 'remarks',
    ];

    protected ?array $retentionCache = null;

    protected function casts(): array
    {
        return ['reorder_cycle_days' => 'integer', 'credit_limit' => 'decimal:2', 'credit_days' => 'integer'];
    }

    protected static function booted(): void
    {
        // Every customer has a debtor ledger in Accounts, kept in step with their details.
        static::saved(fn (Customer $customer) => Ledger::syncCustomer($customer));
        static::deleting(fn (Customer $customer) => $customer->ledger?->delete());
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /** Where goods go: the shipping address, else the billing address. */
    public function deliveryAddress(): ?string
    {
        return $this->shipping_address ?: $this->address;
    }

    /** What their ledger says they owe (debit positive). */
    public function ledgerBalance(): float
    {
        return $this->ledger ? Ledger::whereKey($this->ledger->id)->withTotals()->sole()->closing() : 0.0;
    }

    /** The latest net rate they ordered each product at, from their approved order history. */
    public function lastRates(array $productIds, ?int $exceptOrderId = null): array
    {
        return $this->orders()
            ->whereIn('product_id', $productIds)
            ->when($exceptOrderId, fn ($q) => $q->where(fn ($q) => $q->whereNull('sales_order_id')->orWhere('sales_order_id', '!=', $exceptOrderId)))
            ->orderBy('order_date')
            ->orderBy('id')
            ->get(['product_id', 'rate'])
            ->pluck('rate', 'product_id')
            ->all();
    }

    public function canBeDeleted(): bool
    {
        return ! $this->orders()->exists() && ! $this->quotations()->exists() && ! $this->salesOrders()->exists() && ! $this->ledger?->lines()->exists();
    }

    public function ledger(): HasOne
    {
        return $this->hasOne(Ledger::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * The customer for a won lead: an existing one with the same mobile or GSTIN (so a repeat
     * enquiry doesn't create a duplicate), otherwise a new one from the lead's details.
     * The lead's follow-ups then show on the customer's page too.
     */
    public static function fromLead(Lead $lead, ?User $by): self
    {
        $customer = self::where('phone', $lead->phone)
            ->when($lead->gstin, fn ($q) => $q->orWhere('gstin', $lead->gstin))
            ->orderBy('id')
            ->first();

        if (! $customer) {
            $qualification = $lead->qualification;
            $customer = new self($lead->only(['company_name', 'contact_person', 'phone', 'email', 'city', 'state', 'address', 'gstin', 'industry', 'source', 'assigned_to']) + [
                'buyer_type' => $qualification?->buyer_type,
                'payment_terms' => $qualification?->payment_terms,
                'status' => 'active',
            ]);
            $customer->created_by = $by?->id;
            $customer->save();
        }

        $lead->customer_id = $customer->id;
        $lead->save();
        $lead->followUps()->update(['customer_id' => $customer->id]);

        return $customer;
    }

    /** See RetentionHealth::evaluate(). */
    public function retention(): array
    {
        return $this->retentionCache ??= RetentionHealth::evaluate($this->orders->pluck('order_date'), $this->reorder_cycle_days);
    }

    public function health(): string
    {
        return $this->retention()['health'];
    }

    public function lifetimeValue(): float
    {
        return (float) $this->orders->sum('amount');
    }

    public function orderCount(): int
    {
        return $this->orders->map(fn (CustomerOrder $o) => $o->order_date->toDateString())->unique()->count();
    }

    /** What they buy, per product: how often, how much, and when the next order should come. */
    public function productHistory(): Collection
    {
        return $this->orders
            ->groupBy(fn (CustomerOrder $o) => $o->product_id ? "p{$o->product_id}" : $o->description)
            ->map(function (Collection $orders) {
                $latest = $orders->sortByDesc(fn (CustomerOrder $o) => $o->order_date->timestamp)->first();
                $retention = RetentionHealth::evaluate($orders->pluck('order_date'), $this->reorder_cycle_days);

                return [
                    'description' => $latest->description,
                    'product_id' => $latest->product_id,
                    'unit' => $latest->unitLabel(),
                    'orders' => $orders->count(),
                    'quantity' => $orders->sum('quantity'),
                    'amount' => (float) $orders->sum('amount'),
                    'last_rate' => $latest->rate,
                ] + $retention;
            })
            ->sortByDesc('amount')
            ->values();
    }

    public function buyerTypeLabel(): ?string
    {
        return BuyerScore::label('buyer_type', $this->buyer_type);
    }

    public function paymentTermsLabel(): ?string
    {
        return BuyerScore::label('payment_terms', $this->payment_terms);
    }

    public function industryLabel(): ?string
    {
        return Lead::INDUSTRIES[$this->industry] ?? null;
    }

    public function sourceLabel(): ?string
    {
        return Lead::SOURCES[$this->source] ?? null;
    }
}
