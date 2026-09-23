<?php

namespace App\Http\Middleware;

use App\Models\CompanyUser;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorizes the authenticated user against the tenant resolved from the path.
 *
 * Path identification says which company, never whether the user may reach it.
 * Without this, the identifier in the URL could expose another customer's data.
 */
class EnsureMembership
{
    /**
     * The request attribute the resolved membership is stored under.
     */
    public const ATTRIBUTE = 'membership';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The token used to authenticate has to belong to the Company.
        $token = $user->currentAccessToken();
        $pat = $token instanceof PersonalAccessToken;
        abort_if($pat && $token->company_id !== tenant()->getTenantKey(), 404);

        // The company of the lookup is the tenant one, from the tenant scope.
        $membership = CompanyUser::where('user_id', $user->id)
            ->where('active', true)
            ->first();

        // Avoid proving the company existence by returning 404 instead of 403.
        abort_if($membership === null, 404);

        $request->attributes->set(self::ATTRIBUTE, $membership);

        return $next($request);
    }
}
