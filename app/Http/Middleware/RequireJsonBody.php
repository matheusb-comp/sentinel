<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request whose body is not declared as JSON.
 * This prevents a form body triggering errors about missing fields.
 */
class RequireJsonBody
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->isJson(), 415, 'The body must be JSON, sent with a JSON content type.');

        return $next($request);
    }
}
