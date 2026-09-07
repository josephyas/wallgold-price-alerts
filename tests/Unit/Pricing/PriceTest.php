<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Pricing\Price;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PriceTest extends TestCase
{
    #[Test]
    public function it_stores_four_decimal_places_as_minor_units(): void
    {
        $price = Price::fromDecimal('2650.12');

        self::assertSame(26_501_200, $price->minor);
        self::assertSame('2650.1200', $price->toDecimal());
        self::assertSame('2650.1200', (string) $price);
        self::assertSame('"2650.1200"', json_encode($price));
    }

    /**
     * @return iterable<string, array{string|int|float, int}>
     */
    public static function decimalInputs(): iterable
    {
        yield 'integer' => [2700, 27_000_000];
        yield 'float' => [2650.1, 26_501_000];
        yield 'string with spaces' => [' 2650.5 ', 26_505_000];
        yield 'rounds half up' => ['2650.12345', 26_501_235];
        yield 'rounds down below half' => ['2650.12344', 26_501_234];
        yield 'smallest unit' => ['0.00005', 1];
    }

    #[Test]
    #[DataProvider('decimalInputs')]
    public function it_parses_decimal_input(string|int|float $input, int $minor): void
    {
        self::assertSame($minor, Price::fromDecimal($input)->minor);
    }

    /**
     * @return iterable<string, array{string|int|float}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'text' => ['abc'];
        yield 'thousands separator' => ['1,000'];
        yield 'empty' => [''];
        yield 'rounds to zero' => ['0.00004'];
        yield 'too large' => ['100000000000'];
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function it_rejects_invalid_input(string|int|float $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Price::fromDecimal($input);
    }

    #[Test]
    public function it_rejects_out_of_range_minor_units(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Price::fromMinor(Price::MAX_MINOR + 1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formattedPrices(): iterable
    {
        yield 'two decimals' => ['2650.12', '2,650.12'];
        yield 'pads to two decimals' => ['2650.5', '2,650.50'];
        yield 'whole number' => ['2650', '2,650.00'];
        yield 'keeps significant decimals' => ['2650.1234', '2,650.1234'];
        yield 'millions' => ['1234567.891', '1,234,567.891'];
    }

    #[Test]
    #[DataProvider('formattedPrices')]
    public function it_formats_for_humans(string $input, string $expected): void
    {
        self::assertSame($expected, Price::fromDecimal($input)->format());
    }

    #[Test]
    public function it_compares_exactly(): void
    {
        $low = Price::fromDecimal('2650.0001');
        $high = Price::fromDecimal('2650.0002');

        self::assertSame(-1, $low->compare($high));
        self::assertSame(1, $high->compare($low));
        self::assertSame(0, $low->compare(Price::fromMinor($low->minor)));

        self::assertTrue($low->isBelow($high));
        self::assertTrue($high->isAbove($low));
        self::assertTrue($low->isAtMost($high));
        self::assertTrue($low->isAtMost($low));
        self::assertTrue($high->isAtLeast($low));
        self::assertTrue($high->isAtLeast($high));
        self::assertTrue($low->equals(Price::fromDecimal('2650.0001')));
        self::assertFalse($low->equals($high));
    }
}
