<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function decimals(): array
    {
        return [
            'whole dollars' => ['10', 10_000_000_000],
            'two decimals' => ['12.50', 12_500_000_000],
            'nine decimals' => ['0.046125000', 46_125_000],
            'one nanodollar' => ['0.000000001', 1],
            'negative' => ['-0.373875', -373_875_000],
        ];
    }

    #[DataProvider('decimals')]
    public function test_it_parses_decimal_strings_exactly(string $decimal, int $nanos): void
    {
        $this->assertSame($nanos, Money::fromDecimal($decimal));
    }

    public function test_it_formats_nanodollars_as_nine_decimals(): void
    {
        $this->assertSame('0.046125000', Money::toDecimal(46_125_000));
        $this->assertSame('-1.000000001', Money::toDecimal(-1_000_000_001));
        $this->assertSame('0.000000000', Money::toDecimal(0));
    }

    public function test_it_rejects_amounts_it_cannot_represent_exactly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('0.0000000001');
    }

    public function test_it_rounds_half_up_for_display(): void
    {
        $this->assertSame('$0.0461', Money::format(46_125_000));
        $this->assertSame('$0.0462', Money::format(46_150_000));
        $this->assertSame('$10.00', Money::format(Money::fromDollars(10), 2));
        $this->assertSame('-$0.3739', Money::format(-373_875_000));
    }
}
