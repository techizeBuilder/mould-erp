<?php

namespace App\Support;

/**
 * Turns what a voucher form asks for into debit / credit lines.
 * Every line is ['ledger_id' => int, 'debit' => float, 'credit' => float].
 */
class VoucherLines
{
    /** Money goes into $debit from $credit, e.g. a receipt debits the bank and credits the customer. */
    public static function simple(int $debit, int $credit, float|string $amount): array
    {
        return [self::dr($debit, $amount), self::cr($credit, $amount)];
    }

    /**
     * GST on a taxable amount: half CGST + half SGST within the state, IGST across states.
     * SGST takes any odd paisa so the parts always add up to the tax.
     *
     * @return array{cgst: float, sgst: float, igst: float, tax: float, total: float}
     */
    public static function gst(float|string $taxable, float|string $rate, bool $interstate): array
    {
        $taxable = round((float) $taxable, 2);
        $tax = round($taxable * (float) $rate / 100, 2);
        $cgst = $interstate ? 0.0 : round($tax / 2, 2);

        return [
            'cgst' => $cgst,
            'sgst' => $interstate ? 0.0 : round($tax - $cgst, 2),
            'igst' => $interstate ? $tax : 0.0,
            'tax' => $tax,
            'total' => round($taxable + $tax, 2),
        ];
    }

    /** Dr customer (total); Cr sales (taxable) and each output GST ledger. $gstLedgers: cgst, sgst, igst => id. */
    public static function sale(int $party, int $salesLedger, float|string $taxable, float|string $rate, bool $interstate, array $gstLedgers): array
    {
        $gst = self::gst($taxable, $rate, $interstate);
        $lines = [self::dr($party, $gst['total']), self::cr($salesLedger, $taxable)];

        foreach (['cgst', 'sgst', 'igst'] as $part) {
            if ($gst[$part] > 0) {
                $lines[] = self::cr($gstLedgers[$part], $gst[$part]);
            }
        }

        return $lines;
    }

    /** Dr purchase (taxable) and each input GST ledger; Cr supplier (total). $gstLedgers: cgst, sgst, igst => id. */
    public static function purchase(int $party, int $purchaseLedger, float|string $taxable, float|string $rate, bool $interstate, array $gstLedgers): array
    {
        $gst = self::gst($taxable, $rate, $interstate);
        $lines = [self::dr($purchaseLedger, $taxable)];

        foreach (['cgst', 'sgst', 'igst'] as $part) {
            if ($gst[$part] > 0) {
                $lines[] = self::dr($gstLedgers[$part], $gst[$part]);
            }
        }
        $lines[] = self::cr($party, $gst['total']);

        return $lines;
    }

    /** Compared in paise, so floating-point dust can't make balanced books look unbalanced. */
    public static function isBalanced(array $lines): bool
    {
        return self::total($lines, 'debit') > 0 && self::paise(self::total($lines, 'debit')) === self::paise(self::total($lines, 'credit'));
    }

    public static function total(array $lines, string $side): float
    {
        return round(array_sum(array_map(fn ($line) => (float) ($line[$side] ?? 0), $lines)), 2);
    }

    public static function paise(float|string|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /** "12,500.00 Dr" for a signed balance where debit is positive. */
    public static function drCr(float|string|null $balance): string
    {
        $balance = round((float) $balance, 2);

        if ($balance == 0) {
            return '0.00';
        }

        return Money::format(abs($balance)).($balance > 0 ? ' Dr' : ' Cr');
    }

    protected static function dr(int $ledger, float|string $amount): array
    {
        return ['ledger_id' => $ledger, 'debit' => round((float) $amount, 2), 'credit' => 0.0];
    }

    protected static function cr(int $ledger, float|string $amount): array
    {
        return ['ledger_id' => $ledger, 'debit' => 0.0, 'credit' => round((float) $amount, 2)];
    }
}
