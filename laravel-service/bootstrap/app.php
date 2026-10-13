<?php

declare(strict_types=1);

use App\Exceptions\McpAuthenticationException;
use App\Exceptions\ServiceNotReadyException;
use App\Exceptions\TenantNotFoundException;
use App\Http\Middleware\AssignTraceContext;
use App\Http\Middleware\AuthenticateMcpService;
use App\Http\Middleware\LogInternalAiRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::group([], base_path('routes/health.php'));

            Route::middleware('internal_ai')
                ->prefix('internal/ai/v1')
                ->name('internalAi.')
                ->group(base_path('routes/internal_ai.php'));

        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignTraceContext::class);

        $middleware->group('internal_ai', [
            LogInternalAiRequest::class,
            AuthenticateMcpService::class,
            'throttle:internal_ai',
            SubstituteBindings::class,
        ]);

        // Throttling is keyed by the authenticated actor, so authentication must run first.
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: AuthenticateMcpService::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('internal/*', 'health/*') || $request->expectsJson(),
        );

        $exceptions->map(TenantNotFoundException::class, fn (TenantNotFoundException $e): NotFoundHttpException => new NotFoundHttpException($e->getMessage(), $e));
        $exceptions->map(McpAuthenticationException::class, fn (McpAuthenticationException $e): UnauthorizedHttpException => new UnauthorizedHttpException('Bearer', 'Unauthenticated.', $e));
        $exceptions->map(ServiceNotReadyException::class, fn (ServiceNotReadyException $e): HttpException => new HttpException(503, $e->getMessage(), $e));
    })
    ->booted(function (): void {
        RateLimiter::for('internal_ai', fn (Request $request): Limit => Limit::perMinute((int) config('internal_ai.rate_limit_per_minute'))
            ->by((string) $request->attributes->get(AuthenticateMcpService::ACTOR_ATTRIBUTE)));
    })
    ->create();
