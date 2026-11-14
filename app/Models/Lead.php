<?php

namespace App\Models;

use App\Models\Concerns\HasMasterCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A product enquiry from a prospective buyer. */
class Lead extends Model
{
    use HasMasterCode;

    public const CODE_PREFIX = 'LD';

    public const SOURCES = [
        'indiamart' => 'IndiaMART',
        'tradeindia' => 'TradeIndia',
        'justdial' => 'Justdial',
        'website' => 'Website',
        'whatsapp' => 'WhatsApp',
        'exhibition' => 'Exhibition / Trade Fair',
        'referral' => 'Referral',
        'existing_customer' => 'Existing Customer',
        'cold_call' => 'Cold Call / Field Visit',
        'walk_in' => 'Walk-in',
        'social_media' => 'Social Media',
        'other' => 'Other',
    ];

    /** Who fills the bottles — decides material, neck and compliance needs. */
    public const INDUSTRIES = [
        'pharma' => 'Pharmaceutical',
        'cosmetics' => 'Cosmetics & Personal Care',
        'food_beverage' => 'Food & Beverage',
        'edible_oil' => 'Edible Oil',
        'water' => 'Packaged Water',
        'agro' => 'Agro-chemicals & Pesticides',
        'homecare' => 'Homecare & Cleaning',
        'lubricants' => 'Lubricants & Industrial Chemicals',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'nurture' => 'Nurture',
        'quoted' => 'Quoted',
        'won' => 'Won',
        'lost' => 'Lost',
        'disqualified' => 'Disqualified',
    ];

    /** Statuses that can be picked by hand; the rest follow from qualification and quotations. */
    public const MANUAL_STATUSES = ['new', 'contacted', 'lost'];

    public const CLOSED_STATUSES = ['won', 'lost', 'disqualified'];

    public const LOST_REASONS = [
        'price' => 'Price too high',
        'competitor' => 'Went to a competitor',
        'moq' => 'Quantity below our MOQ',
        'no_mould' => 'No mould / mould cost too high',
        'lead_time' => 'Delivery time too long',
        'sample_rejected' => 'Sample rejected',
        'quality' => 'Spec / quality not met',
        'no_response' => 'Buyer stopped responding',
        'dropped' => 'Requirement dropped',
        'other' => 'Other',
    ];

    protected $fillable = [
        'enquiry_date', 'company_name', 'contact_person', 'phone', 'email', 'city', 'state', 'address', 'gstin', 'industry',
        'source', 'source_detail', 'status', 'lost_reason', 'expected_close_date', 'assigned_to', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'enquiry_date' => 'date',
            'expected_close_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(LeadItem::class);
    }

    public function qualification(): HasOne
    {
        return $this->hasOne(BuyerQualification::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('status', self::CLOSED_STATUSES);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function canBeQuoted(): bool
    {
        return $this->status !== 'disqualified';
    }

    /** Pieces per month across every product asked for. */
    public function estimatedMonthlyVolume(): int
    {
        return (int) $this->items->sum(fn (LeadItem $item) => $item->monthlyQuantity());
    }

    /** The mould answer the requirement points to, pre-filled on the qualification form. */
    public function suggestedMouldFit(): string
    {
        $statuses = $this->items->pluck('mould_status')->unique();

        return match (true) {
            $statuses->contains('new') => 'new_buyer_funded',
            $statuses->contains('existing') => 'existing',
            $statuses->contains('trading') => 'trading',
            default => 'existing',
        };
    }

    /** Applies a qualification decision, unless a quotation has already moved the lead past that stage. */
    public function applyQualificationDecision(string $decision): void
    {
        if (in_array($this->status, ['quoted', 'won'], true)) {
            return;
        }

        $this->status = match ($decision) {
            'qualified', 'nurture', 'disqualified' => $decision,
            default => $this->status === 'new' ? 'new' : 'contacted',
        };
        $this->save();
    }

    /** Keeps the lead in step with its quotations: any won → won, any open → quoted, all lost → lost. */
    public function syncStatusFromQuotations(): void
    {
        $quotations = $this->quotations()->current()->latest('id')->get();

        if ($quotations->contains('status', 'won')) {
            $this->status = 'won';
            $this->lost_reason = null;
        } elseif ($quotations->contains(fn (Quotation $q) => $q->isOpen())) {
            $this->status = 'quoted';
            $this->lost_reason = null;
        } elseif ($lost = $quotations->firstWhere('status', 'lost')) {
            $this->status = 'lost';
            $this->lost_reason = $lost->lost_reason;
        }

        $this->save();
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst((string) $this->source);
    }

    public function industryLabel(): ?string
    {
        return self::INDUSTRIES[$this->industry] ?? null;
    }

    public function lostReasonLabel(): ?string
    {
        return self::LOST_REASONS[$this->lost_reason] ?? null;
    }

    public function statusTone(): string
    {
        return self::tone($this->status);
    }

    public static function tone(?string $status): string
    {
        return match ($status) {
            'new' => 'bg-brand-50 text-brand-700',
            'contacted' => 'bg-sky-50 text-sky-700',
            'qualified', 'won' => 'bg-emerald-50 text-emerald-700',
            'nurture', 'quoted' => 'bg-amber-50 text-amber-700',
            'lost', 'disqualified' => 'bg-red-50 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
