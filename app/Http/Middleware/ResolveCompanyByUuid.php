<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets tenant paths address a company by its uuid as well as by its slug.
 *
 * Path identification resolves a single column, so a uuid in the path is
 * swapped for the company's slug before identification runs. A slug never has
 * the format of a uuid, so the two cannot be confused. The format is checked
 * before querying: Postgres rejects a non-uuid value for a uuid column.
 */
class ResolveCompanyByUuid
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $parameter = PathTenantResolver::tenantParameterName();
        $identifier = $route->parameter($parameter);

        if (is_string($identifier) && Str::isUuid($identifier)) {
            $slug = Company::where('uuid', $identifier)->value('slug');

            if ($slug !== null) {
                $route->setParameter($parameter, $slug);
            }
        }

        return $next($request);
    }
}
