<?php

declare(strict_types=1);

namespace Tests;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Index\InMemoryAlertIndex;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // Feature tests run against an in-memory index that is ready from the
        // start, so alerts created through the application are visible to the
        // matcher without going through the rebuild path.
        $this->app->singleton(AlertIndex::class, fn (): AlertIndex => (new InMemoryAlertIndex)->markReady());
    }
}
