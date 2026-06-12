<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->app['events']->listen(SocialiteWasCalled::class, function ($event) {
            $event->extendSocialite('azure', \SocialiteProviders\Azure\Provider::class);
        });

        // Global API limit: per user when authenticated, per IP otherwise.
        // Auth routes keep their stricter throttle:5,1.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });
    }
}
