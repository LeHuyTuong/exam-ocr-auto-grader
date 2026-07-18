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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('api', [
            'throttle:60,1',
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([]);

        if (app()->isProduction()) {
            $exceptions->renderable(function (\Throwable $e, $request) {
                if (! ($request->expectsJson() || $request->is('api/*'))) {
                    return null;
                }

                // Let Laravel's own handler render 401/403/404/422 with their
                // normal shape (validation errors, etc). Only mask real 5xx.
                $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    ? $e->getStatusCode()
                    : 500;

                if ($status < 500) {
                    return null;
                }

                return response()->json([
                    'error' => 'SERVER_ERROR',
                    'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại sau.',
                ], $status);
            });
        }
    })->create();
