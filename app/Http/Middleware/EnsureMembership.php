<?php

namespace App\Http\Middleware;

use App\Models\CompanyUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorizes the authenticated user against the tenant resolved from the path.
 *
 * Path identification says which company, never whether the user may reach it.
 * Without this middleware, changing the identifier in the URL would expose
 * another customer's data.
 *
 * The company_id filter is explicit rather than left to the tenant scope or to
 * RLS. This is the most security-critical query in the application, and it
 * should not depend on a layer that could be misconfigured elsewhere.
 */
class EnsureMembership
{
    /**
     * The request attribute the resolved membership is stored under.
     */
    public const ATTRIBUTE = 'membership';

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();
        $user = $request->user();

        // Fails closed on a route that lacks authentication or tenant identification.
        abort_if($user === null, 401);
        abort_if($tenant === null, 404);

        $membership = CompanyUser::where('company_id', $tenant->getTenantKey())
            ->where('user_id', $user->id)
            ->where('active', true)
            ->first();

        // Avoid proving the company existence by returning 404 instead of 403.
        abort_if($membership === null, 404);

        $request->attributes->set(self::ATTRIBUTE, $membership);

        return $next($request);
    }
}
