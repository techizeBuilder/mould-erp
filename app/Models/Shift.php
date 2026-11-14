<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Shift extends Model
{
    /** Badge colours a shift can be shown in on the roster (full class names, so Tailwind keeps them). */
    public const COLOURS = [
        'sky' => 'bg-sky-100 text-sky-800',
        'amber' => 'bg-amber-100 text-amber-800',
        'violet' => 'bg-violet-100 text-violet-800',
        'emerald' => 'bg-emerald-100 text-emerald-800',
        'rose' => 'bg-rose-100 text-rose-800',
        'slate' => 'bg-slate-200 text-slate-800',
    ];

    protected $fillable = ['name', 'start_time', 'end_time', 'break_minutes', 'grace_minutes', 'colour', 'is_active', 'remarks'];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function isOvernight(): bool
    {
        return $this->end_time <= $this->start_time;
    }

    /** Scheduled working minutes, excluding the break. */
    public function workMinutes(): int
    {
        return self::minutesBetween($this->start_time, $this->end_time) - $this->break_minutes;
    }

    public function hoursLabel(): string
    {
        return rtrim(rtrim(number_format($this->workMinutes() / 60, 2), '0'), '.').' h';
    }

    /** "08:00 – 20:00" */
    public function timing(): string
    {
        return substr($this->start_time, 0, 5).' – '.substr($this->end_time, 0, 5).($this->isOvernight() ? ' (next day)' : '');
    }

    public function tone(): string
    {
        return self::COLOURS[$this->colour] ?? self::COLOURS['slate'];
    }

    /** Minutes from $from to $to (both "HH:MM[:SS]"), crossing midnight when $to is earlier. */
    public static function minutesBetween(string $from, string $to): int
    {
        $start = Carbon::createFromTimeString($from);
        $end = Carbon::createFromTimeString($to);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }
}
