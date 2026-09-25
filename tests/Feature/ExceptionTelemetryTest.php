<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ExceptionTelemetryTest extends TestCase
{
    public function test_unexpected_failure_is_correlated_without_logging_sensitive_exception_details(): void
    {
        Log::spy();
        config(['app.debug' => false]);
        Route::get('/test-telemetry-failure', fn () => throw new RuntimeException('secret@example.test password=never-log'));

        $response = $this->get('/test-telemetry-failure?token=never-log');
        $response->assertStatus(500)->assertDontSee('never-log');
        $requestId = $response->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid($requestId));
        Log::shouldHaveReceived('error')->once()->with('application.exception', Mockery::on(fn (array $context) => $context['exception_class'] === RuntimeException::class
            && $context['request_id'] === $requestId
            && ! str_contains(json_encode($context), 'never-log')));
        Log::shouldHaveReceived('info')->once()->with('http.request.completed', Mockery::on(fn (array $context) => $context['request_id'] === $requestId && $context['status'] === 500));
    }
}
