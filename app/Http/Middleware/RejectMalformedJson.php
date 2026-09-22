<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request that declares a JSON body and sends one that does not
 * parse, so it does not reach validation disguised as missing fields.
 *
 * An empty body passes: some clients declare JSON on requests without one.
 */
class RejectMalformedJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();

        abort_if($request->isJson() && $content !== '' && ! json_validate($content), 400, 'The body is not valid JSON.');

        return $next($request);
    }
}
