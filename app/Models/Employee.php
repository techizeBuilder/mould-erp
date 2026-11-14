<?php

namespace App\Models;

use App\Models\Concerns\HasMasterCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * An employee record managed by HR. Employees are not users and cannot log in.
 */
class Employee extends Model
{
    use HasMasterCode;

    public const CODE_PREFIX = 'EMP';

    public const STATUSES = [
        'active' => 'Active',
        'left' => 'Left',
    ];

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    public const EMPLOYMENT_TYPES = [
        'permanent' => 'Permanent',
        'contract' => 'Contract',
        'temporary' => 'Temporary',
        'trainee' => 'Trainee',
    ];

    public const WEEKDAYS = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    /** Monthly salary components, in slip order. */
    public const SALARY_COMPONENTS = [
        'basic' => 'Basic',
        'hra' => 'HRA',
        'conveyance' => 'Conveyance Allowance',
        'medical' => 'Medical Allowance',
        'special_allowance' => 'Special Allowance',
        'other_allowance' => 'Other Allowance',
    ];

    protected $fillable = [
        'name', 'father_name', 'gender', 'date_of_birth', 'phone', 'email', 'address',
        'department', 'designation', 'employment_type', 'joining_date', 'leaving_date', 'weekly_off', 'shift_id',
        'basic', 'hra', 'conveyance', 'medical', 'special_allowance', 'other_allowance', 'ot_rate_per_hour',
        'pf_applicable', 'esi_applicable', 'professional_tax', 'uan', 'pf_number', 'esi_number', 'pan', 'aadhaar',
        'bank_name', 'bank_account', 'ifsc', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'leaving_date' => 'date',
            'weekly_off' => 'integer',
            'basic' => 'decimal:2',
            'hra' => 'decimal:2',
            'conveyance' => 'decimal:2',
            'medical' => 'decimal:2',
            'special_allowance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
            'ot_rate_per_hour' => 'decimal:2',
            'pf_applicable' => 'boolean',
            'esi_applicable' => 'boolean',
            'professional_tax' => 'decimal:2',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /** Employees on the rolls at any time between the two dates (inclusive). */
    public function scopeEmployedBetween(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->whereDate('joining_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('leaving_date')->orWhereDate('leaving_date', '>=', $from));
    }

    public function isEmployedOn(Carbon $date): bool
    {
        return $this->joining_date->lte($date) && (! $this->leaving_date || $this->leaving_date->gte($date));
    }

    public function isWeeklyOff(Carbon $date): bool
    {
        return $date->dayOfWeek === $this->weekly_off;
    }

    /**
     * The shift the employee works on $date: an assignment covering the date, else the default shift.
     * Pass preloaded assignments to avoid a query per day.
     */
    public function shiftOn(Carbon $date, ?Collection $assignments = null): ?Shift
    {
        $assignments ??= $this->shiftAssignments()->with('shift')->get();
        $day = $date->toDateString();

        $assignment = $assignments->first(fn (ShiftAssignment $a) => $a->start_date->toDateString() <= $day && $a->end_date->toDateString() >= $day);

        return $assignment?->shift ?? $this->shift;
    }

    public function fixedGross(): float
    {
        return round(collect(array_keys(self::SALARY_COMPONENTS))->sum(fn ($c) => (float) $this->{$c}), 2);
    }

    public function weeklyOffLabel(): string
    {
        return self::WEEKDAYS[$this->weekly_off] ?? '—';
    }

    public function employmentTypeLabel(): ?string
    {
        return self::EMPLOYMENT_TYPES[$this->employment_type] ?? null;
    }

    public function genderLabel(): ?string
    {
        return self::GENDERS[$this->gender] ?? null;
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    }
}
