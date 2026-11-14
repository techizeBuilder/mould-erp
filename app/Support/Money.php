<?php

namespace App\Support;

/**
 * Rupee amounts in Indian digit grouping (12,34,567.50) and in words for salary slips.
 */
class Money
{
    public static function format(float|string|null $amount, int $decimals = 2): string
    {
        $amount = (float) $amount;
        $negative = $amount < 0;
        [$whole, $fraction] = array_pad(explode('.', number_format(abs($amount), $decimals, '.', '')), 2, null);

        // Last three digits, then groups of two.
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $grouped = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3 : $last3;

        return ($negative ? '-' : '').$grouped.($fraction !== null ? '.'.$fraction : '');
    }

    /** "Rupees Twelve Thousand Three Hundred Forty-Five and Fifty Paise Only" */
    public static function inWords(float|string $amount): string
    {
        $amount = round(abs((float) $amount), 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = 'Rupees '.($rupees ? self::words($rupees) : 'Zero');
        if ($paise) {
            $words .= ' and '.self::words($paise).' Paise';
        }

        return $words.' Only';
    }

    protected static function words(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $belowHundred = fn (int $n) => $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)].($n % 10 ? '-'.$ones[$n % 10] : ''));

        $parts = [];
        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand', 100 => 'Hundred'] as $size => $name) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                $parts[] = ($count >= 100 ? self::words($count) : $belowHundred($count)).' '.$name;
                $n %= $size;
            }
        }
        if ($n) {
            $parts[] = $belowHundred($n);
        }

        return implode(' ', $parts);
    }
}
