<?php

namespace App\Support;

/**
 * Quotation arithmetic. Discount applies to goods only; the one-time mould charge and
 * freight are not discounted, and GST applies to everything.
 * Mirrored by the live totals in sales/quotations/form.
 */
class QuotationTotals
{
    public static function lineAmount(int|float|string $quantity, int|float|string $rate): float
    {
        return round((float) $quantity * (float) $rate, 2);
    }

    /**
     * @param  iterable<array{quantity: mixed, rate: mixed, mould_charge?: mixed}>  $lines
     * @return array{subtotal: float, discount_amount: float, mould_total: float, tax_amount: float, total: float}
     */
    public static function compute(iterable $lines, float|string|null $discountPercent = 0, float|string|null $freight = 0, float|string|null $gstPercent = 0): array
    {
        $subtotal = 0.0;
        $mould = 0.0;
        foreach ($lines as $line) {
            $subtotal += self::lineAmount($line['quantity'], $line['rate']);
            $mould += (float) ($line['mould_charge'] ?? 0);
        }

        $discount = round($subtotal * (float) $discountPercent / 100, 2);
        $taxable = $subtotal - $discount + $mould + (float) $freight;
        $tax = round($taxable * (float) $gstPercent / 100, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => $discount,
            'mould_total' => round($mould, 2),
            'tax_amount' => $tax,
            'total' => round($taxable + $tax, 2),
        ];
    }
}
