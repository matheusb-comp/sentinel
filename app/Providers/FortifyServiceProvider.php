<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->pointEmailLinksAtFrontend();

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Points the links in auth emails at the frontend, mirroring the paths of
     * Fortify's routes.
     *
     * The verification link keeps the API's signed query string. The frontend
     * forwards the path and query to the API along with the user's session.
     */
    protected function pointEmailLinksAtFrontend(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return config('app.frontend_url').'/reset-password/'.$token.'?'.http_build_query([
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        VerifyEmail::createUrlUsing(function (User $user): string {
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
            );

            return config('app.frontend_url').parse_url($verificationUrl, PHP_URL_PATH).'?'.parse_url($verificationUrl, PHP_URL_QUERY);
        });
    }
}
