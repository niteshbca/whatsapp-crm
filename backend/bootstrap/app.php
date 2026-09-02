<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->alias([
            'provider.token' => \App\Http\Middleware\EnsureProviderToken::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$directories = [
    storage_path('framework/sessions'),
    storage_path('framework/cache/data'),
    storage_path('framework/views'),
    storage_path('logs'),
];

foreach ($directories as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }

    @chmod($directory, 0777);
}
