<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides a whole half of the tool module when this deployment does not run it.
 * A disabled feature answers 404, not 403: it is absent, not forbidden.
 * Whether a given person may act inside an enabled feature is a policy
 * question, answered with 403 in the usual place.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Features::enabled($feature), 404);

        return $next($request);
    }
}
