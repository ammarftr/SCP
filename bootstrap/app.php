<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hanya percaya header X-Forwarded-* dari proxy yang diizinkan di .env:
        //   TRUSTED_PROXIES=127.0.0.1            (satu/lebih IP proxy, pisahkan dengan koma)
        // Kosong = tidak ada proxy yang dipercaya (alamat IP tidak bisa dipalsukan lewat header).
        // Jangan pakai '*' kecuali server hanya bisa dijangkau lewat proxy.
        $proxies = env('TRUSTED_PROXIES');
        if ($proxies) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        $middleware->alias([
            'peran' => \App\Http\Middleware\PastikanPeran::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
