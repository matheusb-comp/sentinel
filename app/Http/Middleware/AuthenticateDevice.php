<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a device by its token and initializes tenancy for its company.
 *
 * The token and its company are resolved before tenancy is initialized, since
 * the tenant scope would hide a token of any other company. The device is only
 * fetched afterwards, on the tenant connection and under the tenant scope, which
 * also proves the token's company is the device's.
 *
 * last_used_at is written at most once an hour, so a device that sends every
 * few seconds does not update the same row on every request.
 */
class AuthenticateDevice
{
    /**
     * The request attribute the authenticated device is stored under.
     */
    public const ATTRIBUTE = 'device';

    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        throw_if($plainTextToken === null, AuthenticationException::class);

        $token = $this->findDeviceToken($plainTextToken);
        $company = $token?->tenant;

        throw_if($company === null, AuthenticationException::class);

        tenancy()->initialize($company);

        $device = Device::whereKey($token->tokenable_id)->whereNull('archived_at')->first();

        throw_if($device === null, AuthenticationException::class);

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subHour())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set(self::ATTRIBUTE, $device);

        return $next($request);
    }

    /**
     * Returns the valid token, or null when expired or not issued to a Device.
     */
    private function findDeviceToken(string $plainTextToken): ?PersonalAccessToken
    {
        $token = PersonalAccessToken::findToken($plainTextToken);

        if (
            $token === null
            || $token->expires_at?->isPast()
            || $token->tokenable_type !== (new Device)->getMorphClass()
        ) {
            return null;
        }

        return $token;
    }
}
