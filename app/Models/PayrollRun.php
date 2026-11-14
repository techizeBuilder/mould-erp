<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One month's payroll. Draft runs can be recalculated and adjusted; finalized runs are locked.
 */
class PayrollRun extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'finalized' => 'Finalized',
    ];

    protected $fillable = ['month', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'finalized_at' => 'datetime',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    public function label(): string
    {
        return $this->month->format('F Y');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return $this->isFinalized() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700';
    }
}
