<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class RootRouteTest extends TestCase
{
    public function test_root_returns_service_metadata_as_json(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJson(['name' => config('app.name'), 'status' => 'ok']);
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
