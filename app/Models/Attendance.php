<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    /** label, short code for the muster roll, paid-day weight, badge tone. */
    public const STATUSES = [
        'present' => ['label' => 'Present', 'short' => 'P', 'paid' => 1, 'tone' => 'bg-emerald-100 text-emerald-800'],
        'half_day' => ['label' => 'Half Day', 'short' => 'HD', 'paid' => 0.5, 'tone' => 'bg-lime-100 text-lime-800'],
        'absent' => ['label' => 'Absent', 'short' => 'A', 'paid' => 0, 'tone' => 'bg-red-100 text-red-800'],
        'paid_leave' => ['label' => 'Paid Leave', 'short' => 'PL', 'paid' => 1, 'tone' => 'bg-sky-100 text-sky-800'],
        'unpaid_leave' => ['label' => 'Unpaid Leave', 'short' => 'UL', 'paid' => 0, 'tone' => 'bg-orange-100 text-orange-800'],
        'weekly_off' => ['label' => 'Weekly Off', 'short' => 'WO', 'paid' => 1, 'tone' => 'bg-slate-200 text-slate-700'],
        'holiday' => ['label' => 'Holiday', 'short' => 'H', 'paid' => 1, 'tone' => 'bg-violet-100 text-violet-800'],
    ];

    protected $fillable = ['employee_id', 'date', 'status', 'shift_id', 'in_time', 'out_time', 'overtime_hours', 'remarks', 'marked_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** Records dated $from to $to inclusive (whereDate, so it also holds where dates carry a time part). */
    public function scopeBetweenDates(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
    }

    public function paidWeight(): float
    {
        return self::STATUSES[$this->status]['paid'] ?? 0;
    }

    public static function short(string $status): string
    {
        return self::STATUSES[$status]['short'] ?? '?';
    }

    public static function tone(string $status): string
    {
        return self::STATUSES[$status]['tone'] ?? 'bg-slate-100 text-slate-600';
    }

    /**
     * Overtime from in/out times: time worked beyond the shift's scheduled hours,
     * rounded down to the half hour. Null when it can't be worked out.
     */
    public static function overtimeFrom(?string $in, ?string $out, ?Shift $shift): ?float
    {
        if (! $in || ! $out || ! $shift) {
            return null;
        }

        $worked = Shift::minutesBetween($in, $out) - $shift->break_minutes;
        $extra = $worked - $shift->workMinutes();

        return $extra > 0 ? floor($extra / 30) / 2 : 0.0;
    }
}
