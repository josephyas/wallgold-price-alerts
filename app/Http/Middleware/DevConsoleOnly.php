<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The development console exposes unauthenticated write endpoints, so it must
 * never answer in production, and can be switched off anywhere else.
 */
final class DevConsoleOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('gold.dev_console') === true && ! app()->isProduction(), 404);

        return $next($request);
    }
}
