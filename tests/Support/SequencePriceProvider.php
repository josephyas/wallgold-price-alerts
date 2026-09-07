<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Pricing\Contracts\PriceProvider;
use App\Pricing\Exceptions\PriceUnavailable;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Serves a predetermined sequence of quotes (or failures) for tests.
 */
final class SequencePriceProvider implements PriceProvider
{
    /** @var list<PriceQuote|Throwable> */
    private array $items;

    /** @var list<PriceQuote> */
    public array $served = [];

    /**
     * @param  iterable<PriceQuote|Throwable|Price|string|int|float>  $items
     */
    public function __construct(iterable $items)
    {
        $this->items = array_map(
            fn (PriceQuote|Throwable|Price|string|int|float $item): PriceQuote|Throwable => match (true) {
                $item instanceof PriceQuote, $item instanceof Throwable => $item,
                $item instanceof Price => new PriceQuote($item, CarbonImmutable::now(), 'test'),
                default => new PriceQuote(Price::fromDecimal($item), CarbonImmutable::now(), 'test'),
            },
            is_array($items) ? array_values($items) : iterator_to_array($items, false),
        );
    }

    public function fetch(): PriceQuote
    {
        $item = array_shift($this->items) ?? PriceUnavailable::because('The scripted price sequence is exhausted.');

        if ($item instanceof Throwable) {
            throw $item;
        }

        $this->served[] = $item;

        return $item;
    }
}
