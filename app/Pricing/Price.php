<?php

declare(strict_types=1);

namespace App\Pricing;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * A gold price with a fixed precision of four decimal places.
 *
 * The value is held as an integer number of minor units (1/10,000), which keeps
 * comparisons exact and lets the same number serve as a Redis sorted-set score
 * without floating-point drift.
 */
final readonly class Price implements JsonSerializable, Stringable
{
    public const int SCALE = 4;

    /** Largest supported value in minor units: 99,999,999,999.9999, well inside a double's exact integer range. */
    public const int MAX_MINOR = 999_999_999_999_999;

    private const string MINOR_PER_UNIT = '10000';

    private function __construct(public int $minor) {}

    public static function fromMinor(int $minor): self
    {
        if ($minor <= 0) {
            throw new InvalidArgumentException('A price must be greater than zero.');
        }

        if ($minor > self::MAX_MINOR) {
            throw new InvalidArgumentException('The price exceeds the supported range.');
        }

        return new self($minor);
    }

    /**
     * Build from a decimal representation, rounding half-up to four places.
     */
    public static function fromDecimal(string|int|float $value): self
    {
        $decimal = match (true) {
            is_int($value) => (string) $value,
            is_float($value) => number_format($value, self::SCALE + 2, '.', ''),
            default => trim($value),
        };

        if (preg_match('/^\d{1,15}(\.\d+)?$/', $decimal) !== 1) {
            throw new InvalidArgumentException(sprintf('[%s] is not a valid price.', $decimal));
        }

        $rounded = bcadd($decimal, '0.'.str_repeat('0', self::SCALE).'5', self::SCALE);

        return self::fromMinor((int) bcmul($rounded, self::MINOR_PER_UNIT, 0));
    }

    /** Canonical decimal string with exactly four decimal places, e.g. "2650.1200". */
    public function toDecimal(): string
    {
        return bcdiv((string) $this->minor, self::MINOR_PER_UNIT, self::SCALE);
    }

    /** Human-friendly rendering with thousands separators and at least two decimals, e.g. "2,650.12". */
    public function format(): string
    {
        [$integer, $fraction] = explode('.', $this->toDecimal());

        $fraction = str_pad(rtrim($fraction, '0'), 2, '0');

        return number_format((float) $integer, 0, '.', ',').'.'.$fraction;
    }

    public function compare(self $other): int
    {
        return $this->minor <=> $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }

    public function isAtLeast(self $other): bool
    {
        return $this->minor >= $other->minor;
    }

    public function isAtMost(self $other): bool
    {
        return $this->minor <= $other->minor;
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimal();
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
