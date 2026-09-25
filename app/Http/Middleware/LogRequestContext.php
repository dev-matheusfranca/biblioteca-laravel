<?php

namespace App\Http\Middleware;

use App\Services\Operations\OperationalMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! is_string($request->attributes->get('request_id'))) {
            $request->attributes->set('request_id', (string) Str::uuid());
        }
        if (! is_float($request->attributes->get('operations.request_started_at'))) {
            $request->attributes->set('operations.request_started_at', microtime(true));
        }

        $response = $next($request);

        $response->headers->set('X-Request-Id', (string) $request->attributes->get('request_id'));

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->attributes->get('operations.request_logged') === true) {
            return;
        }
        $request->attributes->set('operations.request_logged', true);

        $startedAt = $request->attributes->get('operations.request_started_at');
        $durationMs = is_float($startedAt) ? max(0, (int) ((microtime(true) - $startedAt) * 1000)) : 0;
        $status = $response->getStatusCode();
        $context = [
            'request_id' => (string) $request->attributes->get('request_id'),
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'status' => $status,
            'duration_ms' => $durationMs,
        ];

        Log::info('http.request.completed', $context);
        app(OperationalMetrics::class)->recordHttp($durationMs, $status);
    }
}
