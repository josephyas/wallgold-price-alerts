<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Price feed
    |--------------------------------------------------------------------------
    |
    | "fake" serves a seeded random walk that can be steered with scripted
    | prices; "goldapi" reads XAU/USD from goldapi.io. The poll interval is
    | bounded by the provider: the fake feed is happy at a few hundred
    | milliseconds, real APIs usually allow far less.
    |
    */

    'provider' => env('GOLD_PRICE_PROVIDER', 'fake'),

    'unit' => env('GOLD_PRICE_UNIT', 'USD/oz'),

    'poll_interval_ms' => (int) env('GOLD_POLL_INTERVAL_MS', 1000),

    // A stored price older than this is treated as unknown.
    'price_max_age_ms' => (int) env('GOLD_PRICE_MAX_AGE_MS', 10000),

    'fake' => [
        'start' => env('GOLD_FAKE_START', '2650.00'),
        'max_step' => env('GOLD_FAKE_MAX_STEP', '2.00'),
        'seed' => env('GOLD_FAKE_SEED'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert index
    |--------------------------------------------------------------------------
    |
    | Active alerts are indexed in Redis sorted sets on this connection so a
    | price tick finds everything it triggers in one atomic call.
    |
    */

    'redis_connection' => env('GOLD_REDIS_CONNECTION', 'default'),

    'goldapi' => [
        'url' => env('GOLD_API_URL', 'https://www.goldapi.io/api/XAU/USD'),
        'token' => env('GOLD_API_TOKEN', ''),
        'timeout' => (float) env('GOLD_API_TIMEOUT', 2.0),
    ],

];
