<?php

namespace App\Models;

use App\Support\BuyerScore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The scored assessment of a lead. Score and grade are always derived from the answers. */
class BuyerQualification extends Model
{
    public const DECISIONS = [
        'pending' => 'Pending',
        'qualified' => 'Qualified',
        'nurture' => 'Nurture later',
        'disqualified' => 'Disqualified',
    ];

    protected $fillable = [
        'buyer_type', 'monthly_volume', 'price_fit', 'mould_fit', 'decision_authority', 'purchase_timeline', 'payment_terms',
        'gst_verified', 'sample_required', 'compliance_required', 'current_supplier', 'decision', 'decision_reason', 'qualified_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_volume' => 'integer',
            'score' => 'integer',
            'gst_verified' => 'boolean',
            'sample_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BuyerQualification $q) {
            $q->score = BuyerScore::score($q->attributesToArray());
            $q->grade = BuyerScore::grade($q->score);
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qualified_by');
    }

    public function recommendation(): string
    {
        return BuyerScore::recommendation($this->score, $this->mould_fit);
    }

    public function gradeLabel(): string
    {
        return BuyerScore::GRADES[$this->grade]['label'] ?? '—';
    }

    public function gradeTone(): string
    {
        return BuyerScore::GRADES[$this->grade]['tone'] ?? 'bg-slate-100 text-slate-600';
    }

    public function decisionLabel(): string
    {
        return self::DECISIONS[$this->decision] ?? '—';
    }

    public static function decisionTone(?string $decision): string
    {
        return match ($decision) {
            'qualified' => 'bg-emerald-50 text-emerald-700',
            'nurture' => 'bg-amber-50 text-amber-700',
            'disqualified' => 'bg-red-50 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
