<?php

namespace App\Support;

/**
 * Bottle/jar capacities are stored in millilitres and shown as ml or L.
 */
class Capacity
{
    public static function format(float|string|null $ml): ?string
    {
        if ($ml === null || $ml === '') {
            return null;
        }

        $ml = (float) $ml;

        return $ml >= 1000
            ? self::trim($ml / 1000).' L'
            : self::trim($ml).' ml';
    }

    /** "500 ml – 2 L" style range; open ends read "up to" / "from". */
    public static function range(float|string|null $min, float|string|null $max): ?string
    {
        return match (true) {
            $min !== null && $max !== null => self::format($min).' – '.self::format($max),
            $min !== null => 'from '.self::format($min),
            $max !== null => 'up to '.self::format($max),
            default => null,
        };
    }

    /** Converts a form value + unit ("ml" or "l") to millilitres. */
    public static function toMl(mixed $value, ?string $unit): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value * (strtolower((string) $unit) === 'l' ? 1000 : 1), 2);
    }

    /** Splits stored ml into [value, unit] for a form. */
    public static function forInput(float|string|null $ml): array
    {
        if ($ml === null || $ml === '') {
            return ['', 'ml'];
        }

        $ml = (float) $ml;

        return $ml >= 1000 && fmod($ml, 1000) == 0 ? [self::trim($ml / 1000), 'l'] : [self::trim($ml), 'ml'];
    }

    public static function trim(float|string $number): string
    {
        return rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.');
    }
}
