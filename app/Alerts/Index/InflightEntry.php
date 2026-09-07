<?php

declare(strict_types=1);

namespace App\Alerts\Index;

use App\Pricing\Price;

/** An alert that was popped from the index and not yet acknowledged. */
final readonly class InflightEntry
{
    public function __construct(
        public int $id,
        public Price $price,
        public int $poppedAtMs,
    ) {}

    public static function fromMember(string $member, int $poppedAtMs): self
    {
        [$id, $minor] = explode(':', $member, 2);

        return new self((int) $id, Price::fromMinor((int) $minor), $poppedAtMs);
    }

    public static function memberFor(int $id, Price $price): string
    {
        return $id.':'.$price->minor;
    }

    public function member(): string
    {
        return self::memberFor($this->id, $this->price);
    }
}
