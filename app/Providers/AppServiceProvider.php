<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Outside production, fail loudly on lazy loading (N+1 queries),
        // silently discarded attributes and access to missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Block migrate:fresh / db:wipe against a production database.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        Password::defaults(function () {
            $rule = Password::min(12)->letters()->mixedCase()->numbers();

            // Checks the HaveIBeenPwned range API (k-anonymity) in production only,
            // so tests and local development never call an external service.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Brute-force protection: per account+IP, plus a wider per-IP ceiling
        // so one client cannot cycle through many email addresses.
        RateLimiter::for('login', function (Request $request) {
            $perMinute = Config::integer('stockflow.rate_limits.login');
            $email = mb_strtolower($request->string('email')->toString());

            return [
                Limit::perMinute($perMinute)->by('login:'.$email.'|'.$request->ip()),
                Limit::perMinute($perMinute * 4)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(Config::integer('stockflow.rate_limits.api'))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('writes', function (Request $request) {
            return Limit::perMinute(Config::integer('stockflow.rate_limits.writes'))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });
    }
}
