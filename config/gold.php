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
        // An integer makes the walk repeatable; empty or absent means a fresh walk each start.
        'seed' => is_numeric($seed = env('GOLD_FAKE_SEED')) ? (int) $seed : null,
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

    'index' => [
        // Alerts taken from the index per call; the matcher clamps this to 1000 (Lua stack bound).
        'pop_batch' => (int) env('GOLD_INDEX_POP_BATCH', 1000),
        // Jobs pushed to the queue per pipelined round trip.
        'dispatch_batch' => (int) env('GOLD_INDEX_DISPATCH_BATCH', 1000),
        // A popped alert not acknowledged within this window, while the alerts
        // queue is idle, is considered lost and dispatched again.
        'inflight_ttl_seconds' => (int) env('GOLD_INDEX_INFLIGHT_TTL_SECONDS', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | A delivery is claimed with a conditional update before the email goes
    | out. A claim older than this that never completed is treated as
    | abandoned (the worker died mid-send) and may be claimed again.
    |
    */

    'delivery' => [
        'stale_after_seconds' => (int) env('GOLD_DELIVERY_STALE_AFTER_SECONDS', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | API limits
    |--------------------------------------------------------------------------
    */

    // Requests per minute per user (or per IP before authentication).
    'api_rate_per_minute' => (int) env('GOLD_API_RATE_PER_MINUTE', 60),

    // Stored alerts (any status) a single user may hold.
    'max_alerts_per_user' => (int) env('GOLD_MAX_ALERTS_PER_USER', 100),

    'goldapi' => [
        'url' => env('GOLD_API_URL', 'https://www.goldapi.io/api/XAU/USD'),
        'token' => env('GOLD_API_TOKEN', ''),
        'timeout' => (float) env('GOLD_API_TIMEOUT', 2.0),
    ],

];
