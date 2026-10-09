<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by('login:'.strtolower((string) $r->input('email')).'|'.$r->ip()), Limit::perMinute(30)->by('login-ip:'.$r->ip())]);
        RateLimiter::for('registration', fn (Request $r) => Limit::perMinute(5)->by('register:'.$r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perMinute(3)->by('reset:'.$r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
    }
}
