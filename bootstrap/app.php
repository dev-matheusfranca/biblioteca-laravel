<?php

use App\Exceptions\IdempotencyConflictException;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureApiTokenAbility;
use App\Http\Middleware\LogRequestContext;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Support\ApiErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(LogRequestContext::class);
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'api.token' => EnsureApiTokenAbility::class,
            'idempotency' => RequireIdempotencyKey::class,
            'request.context' => LogRequestContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            $requestId = request()->attributes->get('request_id');
            if (is_string($requestId)) {
                $response->headers->set('X-Request-Id', $requestId);
            }

            return $response;
        });
        $exceptions->report(function (Throwable $exception): bool {
            // Exception messages and traces may embed SQL bindings, e-mail addresses or credentials.
            Log::error('application.exception', [
                'exception_class' => $exception::class,
                'source' => basename($exception->getFile()),
                'line' => $exception->getLine(),
                'request_id' => app()->bound('request') ? request()->attributes->get('request_id') : null,
            ]);

            return false;
        });
        $isApi = fn (Request $request): bool => $request->is('api/*');
        $exceptions->dontReport([IdempotencyConflictException::class]);
        $exceptions->dontReportWhen(fn (Throwable $exception): bool => $exception instanceof DomainException
            && app()->bound('request')
            && request()->is('api/*'));
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $isApi($request) || $request->expectsJson());
        $exceptions->render(fn (IdempotencyConflictException $exception, Request $request) => $isApi($request)
            ? ApiErrorResponse::make($request, $exception->getMessage(), 409)
            : null);
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $isApi($request)
            ? ApiErrorResponse::make($request, 'Não autenticado.', 401)
            : null);
        $exceptions->render(fn (AccessDeniedHttpException $exception, Request $request) => $isApi($request)
            ? ApiErrorResponse::make($request, $exception->getMessage() ?: 'Acesso não autorizado.', 403)
            : null);
        $exceptions->render(fn (ValidationException $exception, Request $request) => $isApi($request)
            ? ApiErrorResponse::make($request, 'Os dados enviados são inválidos.', 422, $exception->errors())
            : null);
        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => $isApi($request)
            ? ApiErrorResponse::make($request, 'Recurso não encontrado.', 404)
            : null);
        $exceptions->render(fn (ThrottleRequestsException $exception, Request $request) => $isApi($request)
            ? ApiErrorResponse::make($request, 'Limite de requisições excedido.', 429, headers: $exception->getHeaders())
            : null);
        $exceptions->render(fn (DomainException $exception, Request $request) => $isApi($request)
            ? ApiErrorResponse::make($request, $exception->getMessage(), 422)
            : null);
        $exceptions->render(fn (HttpExceptionInterface $exception, Request $request) => $isApi($request)
            ? ApiErrorResponse::make(
                $request,
                $exception->getStatusCode() < 500 && $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Não foi possível processar a requisição.',
                $exception->getStatusCode(),
                headers: $exception->getHeaders(),
            )
            : null);
        $exceptions->render(fn (Throwable $exception, Request $request) => $isApi($request)
            ? ApiErrorResponse::make($request, 'Não foi possível processar a requisição.', 500)
            : null);
    })->create();
