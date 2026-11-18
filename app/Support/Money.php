<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact money handling.
 *
 * All amounts in the app are integers of nanodollars (1 USD = 1,000,000,000
 * nanodollars), the same precision Pixelforge reports in (nine decimals).
 * Integers never drift the way floats do, and sums in SQL stay exact.
 */
final class Money
{
    public const int NANOS_PER_DOLLAR = 1_000_000_000;

    /**
     * Parse an exact decimal dollar string such as "12.50" or "0.046125000".
     */
    public static function fromDecimal(string $amount): int
    {
        if (preg_match('/^(-)?(\d+)(?:\.(\d{1,9}))?$/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException("[{$amount}] is not a decimal amount with at most nine decimal places.");
        }

        $nanos = (int) $matches[2] * self::NANOS_PER_DOLLAR
            + (int) str_pad($matches[3] ?? '', 9, '0');

        return $matches[1] === '-' ? -$nanos : $nanos;
    }

    /**
     * Format nanodollars as an exact nine-decimal string, e.g. "0.046125000".
     */
    public static function toDecimal(int $nanos): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $nanos = abs($nanos);

        return sprintf(
            '%s%d.%09d',
            $sign,
            intdiv($nanos, self::NANOS_PER_DOLLAR),
            $nanos % self::NANOS_PER_DOLLAR,
        );
    }

    /**
     * Convert whole dollars to nanodollars.
     */
    public static function fromDollars(int $dollars): int
    {
        return $dollars * self::NANOS_PER_DOLLAR;
    }

    /**
     * Format nanodollars for people, e.g. "$0.0461", rounding half up.
     * Only for display: stored and compared amounts are always exact.
     */
    public static function format(int $nanos, int $decimals = 4): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $unit = 10 ** (9 - $decimals);
        $rounded = intdiv(abs($nanos) + intdiv($unit, 2), $unit);
        $scale = 10 ** $decimals;

        return sprintf(
            "%s$%d.%0{$decimals}d",
            $sign,
            intdiv($rounded, $scale),
            $rounded % $scale,
        );
    }
}
