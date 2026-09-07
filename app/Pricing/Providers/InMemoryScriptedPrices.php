<?php

declare(strict_types=1);

namespace App\Pricing\Providers;

use App\Pricing\Contracts\ScriptedPrices;
use App\Pricing\Price;

final class InMemoryScriptedPrices implements ScriptedPrices
{
    /** @var list<Price> */
    private array $queue = [];

    public function pull(): ?Price
    {
        return array_shift($this->queue);
    }

    public function push(Price ...$prices): void
    {
        array_push($this->queue, ...$prices);
    }

    public function clear(): void
    {
        $this->queue = [];
    }
}
