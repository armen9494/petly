/**
 * Money arrives from the server as exact nine-decimal dollar strings, such as
 * "0.046125000". These helpers compare and format them with BigInt, never
 * floating point, so what we show is exactly what was recorded.
 */
const NANOS_PER_DOLLAR = 1_000_000_000n;

export function toNanos(decimal) {
    const negative = decimal.startsWith('-');
    const [whole, fraction = ''] = decimal.replace('-', '').split('.');
    const nanos = BigInt(whole) * NANOS_PER_DOLLAR + BigInt(fraction.padEnd(9, '0').slice(0, 9));

    return negative ? -nanos : nanos;
}

/**
 * Format a decimal string as dollars, rounding half up to `digits` decimals.
 * Whole cents drop the extra zeros: "0.046125000" is "$0.0461", "10" is "$10.00".
 */
export function formatUsd(decimal, digits = 4) {
    if (decimal === null || decimal === undefined) {
        return '—';
    }

    let nanos = toNanos(decimal);
    const negative = nanos < 0n;

    if (negative) {
        nanos = -nanos;
    }

    const unit = 10n ** BigInt(9 - digits);
    const scale = 10n ** BigInt(digits);
    const rounded = (nanos + unit / 2n) / unit;
    const whole = (rounded / scale).toLocaleString('en-US');
    const fraction = digits > 0 ? `.${(rounded % scale).toString().padStart(digits, '0').replace(/^(\d{2})0+$/, '$1')}` : '';

    return `${negative ? '-' : ''}$${whole}${fraction}`;
}

/**
 * The inverse of toNanos: a whole number of nanodollars as a nine-decimal string.
 */
export function fromNanos(nanos) {
    let value = BigInt(Math.round(Number(nanos)));
    const negative = value < 0n;

    if (negative) {
        value = -value;
    }

    const whole = value / NANOS_PER_DOLLAR;
    const fraction = (value % NANOS_PER_DOLLAR).toString().padStart(9, '0');

    return `${negative ? '-' : ''}${whole}.${fraction}`;
}
