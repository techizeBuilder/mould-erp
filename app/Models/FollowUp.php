<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scheduled contact with a buyer, about a lead, a quotation or a customer.
 * A quotation follow-up also carries that quotation's lead / customer, so it shows on their pages too.
 */
class FollowUp extends Model
{
    public const TYPES = [
        'call' => 'Phone Call',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'visit' => 'Factory / Site Visit',
        'meeting' => 'Meeting',
    ];

    public const PURPOSES = [
        'requirement' => 'Understand requirement',
        'sample_dispatch' => 'Sample dispatch',
        'sample_feedback' => 'Sample feedback',
        'quotation' => 'Quotation follow-up',
        'negotiation' => 'Price negotiation',
        'mould_approval' => 'Mould / drawing approval',
        'order_confirmation' => 'Order confirmation',
        'reorder' => 'Reorder reminder',
        'payment' => 'Payment follow-up',
        'feedback' => 'Product feedback / complaint',
        'general' => 'General',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'done' => 'Done',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = ['lead_id', 'quotation_id', 'customer_id', 'product_id', 'type', 'purpose', 'due_at', 'assigned_to', 'agenda'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->pending()->whereDate('due_at', '<', today());
    }

    public function scopeDueToday(Builder $query): void
    {
        $query->pending()->whereDate('due_at', today());
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->pending()->whereDate('due_at', '>', today());
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_at->copy()->startOfDay()->lt(today());
    }

    /** Records what happened; a lead that was still new now counts as contacted. */
    public function complete(string $outcome, ?User $by): void
    {
        $this->forceFill(['status' => 'done', 'outcome' => $outcome, 'completed_at' => now(), 'completed_by' => $by?->id])->save();

        if ($this->lead && $this->lead->status === 'new') {
            $this->lead->update(['status' => 'contacted']);
        }
    }

    /** The next follow-up in the same conversation. */
    public function scheduleNext(array $attributes, ?User $by): self
    {
        $next = new self($attributes + $this->only(['lead_id', 'quotation_id', 'customer_id', 'product_id', 'type', 'purpose', 'assigned_to']));
        $next->created_by = $by?->id;
        $next->save();

        return $next;
    }

    public function buyerName(): string
    {
        return $this->customer?->company_name ?? $this->lead?->company_name ?? $this->quotation?->buyerName() ?? '—';
    }

    public function buyerPhone(): ?string
    {
        return $this->customer?->phone ?? $this->lead?->phone;
    }

    /** [label, route name, model] of the record this follow-up is about. */
    public function subject(): ?array
    {
        return match (true) {
            (bool) $this->quotation => [$this->quotation->number(), 'sales.quotations.show', $this->quotation],
            (bool) $this->lead => [$this->lead->code, 'sales.leads.show', $this->lead],
            (bool) $this->customer => [$this->customer->code, 'sales.retention.show', $this->customer],
            default => null,
        };
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? (string) $this->type;
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? (string) $this->purpose;
    }

    public function statusLabel(): string
    {
        return $this->isOverdue() ? 'Overdue' : (self::STATUSES[$this->status] ?? (string) $this->status);
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->isOverdue() => 'bg-red-50 text-red-700',
            $this->status === 'pending' => 'bg-amber-50 text-amber-700',
            $this->status === 'done' => 'bg-emerald-50 text-emerald-700',
            default => 'bg-slate-100 text-slate-500',
        };
    }
}
