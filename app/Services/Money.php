<?php

namespace App\Services;

/** PKR money arithmetic in paisa, with decimal strings at the DB boundary. */
final class Money
{
    public static function minor(string|int|float $value): int
    {
        $negative = str_starts_with((string) $value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim((string) $value, '-'), 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');
        $minor = (int) $whole * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return $negative ? -$minor : $minor;
    }

    public static function decimal(int $minor): string
    {
        return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function publicAmount(int $minor): float
    {
        return $minor / 100;
    }
}
