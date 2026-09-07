<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Pricing\Contracts\ScriptedPrices;
use App\Pricing\Price;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('price:fake-push {prices* : Prices the fake feed will serve next, in order}')]
#[Description('Steer the fake price feed to exact values')]
final class PushFakePrice extends Command
{
    public function handle(ScriptedPrices $script): int
    {
        /** @var list<string> $arguments */
        $arguments = $this->argument('prices');

        try {
            $prices = array_map(Price::fromDecimal(...), $arguments);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        }

        $script->push(...$prices);

        $this->components->info(sprintf(
            'Queued %d price(s) for the fake feed: %s',
            count($prices),
            implode(', ', array_map(fn (Price $price): string => $price->toDecimal(), $prices)),
        ));

        return self::SUCCESS;
    }
}
