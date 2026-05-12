<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\BasicAuthUseName;
use App\Http\Middleware\DisableCsrfToken;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('otp:clean-expired')->everyThirtySeconds();
        $schedule->command('sms:timeout-reminder')->everyThirtySeconds();
        $schedule->command('sms:checkout-reminder')->everyThirtySeconds();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.basic.name' => BasicAuthUseName::class,
            'csrf-token.disable' => DisableCsrfToken::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
