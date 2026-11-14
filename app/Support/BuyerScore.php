<?php

namespace App\Support;

/**
 * Scores a buyer out of 100 on how good a fit they are for a bottle manufacturer:
 * volume, what kind of buyer, price, whether we have (or can get) the mould, who we
 * are talking to, when they will buy, and how they will pay.
 *
 * Mirrored by the live preview in sales/qualification/form.
 */
class BuyerScore
{
    /** Each option is value => [label, points]. */
    public const CRITERIA = [
        'buyer_type' => ['label' => 'Buyer type', 'options' => [
            'brand_owner' => ['Brand owner / filler', 15],
            'contract' => ['Contract manufacturer', 15],
            'distributor' => ['Distributor / wholesaler', 10],
            'trader' => ['Trader', 6],
            'retail' => ['Small / retail buyer', 3],
        ]],
        'price_fit' => ['label' => 'Target price vs our price', 'options' => [
            'at_or_above' => ['At or above our price', 15],
            'negotiable' => ['Within 5% — negotiable', 10],
            'unknown' => ['Not known yet', 5],
            'below' => ['Well below our price', 2],
        ]],
        'mould_fit' => ['label' => 'Mould / technical fit', 'options' => [
            'existing' => ['Existing mould available', 20],
            'trading' => ['We can trade (buy & resell)', 14],
            'new_buyer_funded' => ['New mould — buyer pays', 14],
            'new_company_funded' => ['New mould — we pay', 8],
            'not_feasible' => ['Cannot be made', 0],
        ]],
        'decision_authority' => ['label' => 'Who we are talking to', 'options' => [
            'decision_maker' => ['Decision maker', 5],
            'influencer' => ['Purchase team / influencer', 3],
            'unknown' => ['Not known yet', 1],
        ]],
        'purchase_timeline' => ['label' => 'Purchase timeline', 'options' => [
            'immediate' => ['Immediate (within a month)', 10],
            'one_to_three' => ['1–3 months', 7],
            'three_to_six' => ['3–6 months', 4],
            'exploring' => ['Only exploring', 1],
        ]],
        'payment_terms' => ['label' => 'Payment terms', 'options' => [
            'advance' => ['100% advance', 10],
            'part_advance' => ['Part advance, balance on delivery', 9],
            'on_delivery' => ['Against delivery', 8],
            'credit_30' => ['30 days credit', 6],
            'credit_60' => ['60+ days credit', 3],
            'unknown' => ['Not discussed', 2],
        ]],
    ];

    /** Minimum pieces per month => points (highest first). */
    public const VOLUME_BANDS = [100000 => 25, 25000 => 20, 5000 => 12, 1000 => 6, 0 => 2];

    public const GRADES = [
        'A' => ['min' => 75, 'label' => 'A – Hot', 'tone' => 'bg-emerald-50 text-emerald-700'],
        'B' => ['min' => 50, 'label' => 'B – Warm', 'tone' => 'bg-sky-50 text-sky-700'],
        'C' => ['min' => 25, 'label' => 'C – Cold', 'tone' => 'bg-amber-50 text-amber-700'],
        'D' => ['min' => 0, 'label' => 'D – Poor', 'tone' => 'bg-red-50 text-red-700'],
    ];

    /** value => label for one criterion, for a select. */
    public static function options(string $criterion): array
    {
        return array_map(fn ($o) => $o[0], self::CRITERIA[$criterion]['options']);
    }

    public static function label(string $criterion, ?string $value): ?string
    {
        return self::CRITERIA[$criterion]['options'][$value][0] ?? null;
    }

    public static function volumePoints(int|float|null $piecesPerMonth): int
    {
        foreach (self::VOLUME_BANDS as $min => $points) {
            if ((float) $piecesPerMonth >= $min) {
                return $points;
            }
        }

        return 0;
    }

    /** $answers holds each CRITERIA key plus monthly_volume; an unknown or missing answer earns nothing. */
    public static function score(array $answers): int
    {
        $score = self::volumePoints($answers['monthly_volume'] ?? 0);

        foreach (self::CRITERIA as $criterion => $definition) {
            $score += $definition['options'][$answers[$criterion] ?? '_'][1] ?? 0;
        }

        return min(100, $score);
    }

    public static function grade(int $score): string
    {
        foreach (self::GRADES as $grade => $definition) {
            if ($score >= $definition['min']) {
                return $grade;
            }
        }

        return 'D';
    }

    /** The decision the score points to; a product we cannot make is never a qualified buyer. */
    public static function recommendation(int $score, ?string $mouldFit = null): string
    {
        if ($mouldFit === 'not_feasible') {
            return 'disqualified';
        }

        return match (self::grade($score)) {
            'A', 'B' => 'qualified',
            'C' => 'nurture',
            default => 'disqualified',
        };
    }
}
