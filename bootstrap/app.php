<?php

use App\Http\Middleware\Role;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $m) {
        $m->alias(['role' => Role::class]);
        $m->append(SecurityHeaders::class);
        $m->authenticateSessions();
        if ($proxies = env('TRUSTED_PROXIES')) {
            $m->trustProxies(at: array_filter(explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $e) {
        $e->shouldRenderJsonWhen(fn (Request $r, Throwable $ex) => $r->is('api/*') || $r->expectsJson());
        $e->render(function (Throwable $ex, Request $r) {
            if (! $r->is('api/*')) {
                return null;
            }
            $status = match (true) {
                $ex instanceof ValidationException => 422,$ex instanceof AuthenticationException => 401,$ex instanceof AuthorizationException => 403,$ex instanceof ModelNotFoundException => 404,$ex instanceof HttpExceptionInterface => $ex->getStatusCode(),default => 500
            };
            $message = match ($status) {
                401 => 'Authentication required.',403 => 'You are not authorized for this action.',404 => 'Resource not found.',422 => 'Validation failed.',429 => 'Too many requests. Please try later.',default => $status >= 500 ? 'An internal error occurred.' : 'Request failed.'
            };

            return response()->json(['message' => $message, 'errors' => $ex instanceof ValidationException ? $ex->errors() : (object) []], $status, $ex instanceof HttpExceptionInterface ? $ex->getHeaders() : []);
        });
    })->create();
