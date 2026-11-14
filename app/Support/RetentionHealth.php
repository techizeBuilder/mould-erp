<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * How a repeat buyer is doing, judged by how long since their last order compared with
 * their reorder cycle (the one set on the customer, else their average gap between
 * orders, else DEFAULT_CYCLE_DAYS).
 */
class RetentionHealth
{
    public const DEFAULT_CYCLE_DAYS = 45;

    public const STATES = [
        'new' => ['label' => 'No orders yet', 'tone' => 'bg-slate-100 text-slate-600'],
        'healthy' => ['label' => 'Healthy', 'tone' => 'bg-emerald-50 text-emerald-700'],
        'due' => ['label' => 'Reorder due', 'tone' => 'bg-sky-50 text-sky-700'],
        'at_risk' => ['label' => 'At risk', 'tone' => 'bg-amber-50 text-amber-700'],
        'lost' => ['label' => 'Lapsed', 'tone' => 'bg-red-50 text-red-700'],
    ];

    /** Average days between distinct order dates, or null with fewer than two. */
    public static function averageGap(iterable $orderDates): ?int
    {
        $days = collect($orderDates)->map(fn ($d) => Carbon::parse($d)->startOfDay()->timestamp)->unique()->sort()->values();

        if ($days->count() < 2) {
            return null;
        }

        return (int) round(($days->last() - $days->first()) / 86400 / ($days->count() - 1));
    }

    public static function state(int $daysSinceLastOrder, int $cycleDays): string
    {
        return match (true) {
            $daysSinceLastOrder < $cycleDays => 'healthy',
            $daysSinceLastOrder < $cycleDays * 1.5 => 'due',
            $daysSinceLastOrder < $cycleDays * 3 => 'at_risk',
            default => 'lost',
        };
    }

    /**
     * @return array{health: string, last_order: ?Carbon, next_expected: ?Carbon, days_since: ?int, cycle: int, average_gap: ?int}
     */
    public static function evaluate(iterable $orderDates, ?int $cycleDays = null, ?CarbonInterface $today = null): array
    {
        $today = Carbon::parse($today ?? Carbon::now())->startOfDay();
        $dates = collect($orderDates)->map(fn ($d) => Carbon::parse($d)->startOfDay())->sortBy(fn ($d) => $d->timestamp)->values();
        $gap = self::averageGap($dates);
        $cycle = $cycleDays ?: ($gap ?: self::DEFAULT_CYCLE_DAYS);

        if ($dates->isEmpty()) {
            return ['health' => 'new', 'last_order' => null, 'next_expected' => null, 'days_since' => null, 'cycle' => $cycle, 'average_gap' => null];
        }

        $last = $dates->last();
        $since = (int) $last->diffInDays($today);

        return [
            'health' => self::state($since, $cycle),
            'last_order' => $last,
            'next_expected' => $last->copy()->addDays($cycle),
            'days_since' => $since,
            'cycle' => $cycle,
            'average_gap' => $gap,
        ];
    }

    public static function label(string $state): string
    {
        return self::STATES[$state]['label'] ?? $state;
    }

    public static function tone(string $state): string
    {
        return self::STATES[$state]['tone'] ?? 'bg-slate-100 text-slate-600';
    }
}
