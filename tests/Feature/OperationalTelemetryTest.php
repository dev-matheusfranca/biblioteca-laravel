<?php

namespace Tests\Feature;

use App\Actions\Communication\DeliverOutboxEvent;
use App\Http\Middleware\LogRequestContext;
use App\Jobs\DeliverCommunication;
use App\Services\Operations\OperationalMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OperationalTelemetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_request_context_logs_only_safe_metadata_once_when_registered_twice(): void
    {
        Log::spy();
        $request = Request::create('/catalogo?api_token=never-log', 'POST', ['password' => 'never-log']);
        $outer = app(LogRequestContext::class);
        $inner = app(LogRequestContext::class);

        $response = $outer->handle($request, fn (Request $current): Response => $inner->handle($current, fn (): Response => new Response('', 422)));
        $inner->terminate($request, $response);
        $outer->terminate($request, $response);

        $this->assertNotSame('', $response->headers->get('X-Request-Id'));
        Log::shouldHaveReceived('info')->once()->with('http.request.completed', Mockery::on(function (array $context): bool {
            return $context['method'] === 'POST'
                && $context['status'] === 422
                && isset($context['request_id'], $context['duration_ms'])
                && ! array_key_exists('query', $context)
                && ! array_key_exists('body', $context)
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'never-log');
        }));

        $summary = app(OperationalMetrics::class)->httpSummary();
        $this->assertSame(1, $summary['count']);
        $this->assertSame(1, $summary['errors']);
    }

    public function test_http_and_job_metrics_are_aggregated_without_retaining_identifiers(): void
    {
        $metrics = app(OperationalMetrics::class);
        $metrics->recordHttp(120, 200);
        $metrics->recordHttp(180, 500);
        $metrics->recordJob('communication_delivery', 'completed', 60);
        $metrics->recordJob('communication_delivery', 'failed_attempt', 90);

        $http = $metrics->httpSummary();
        $jobs = $metrics->jobSummary();

        $this->assertSame(['count' => 2, 'errors' => 1, 'average_latency_ms' => 150, 'max_latency_ms' => 180, 'window_minutes' => 15], $http);
        $this->assertSame(['count' => 2, 'errors' => 1, 'average_latency_ms' => 75, 'max_latency_ms' => 90, 'window_minutes' => 15], $jobs);
    }

    public function test_communication_job_records_an_aggregate_outcome_without_altering_delivery_call(): void
    {
        Log::spy();
        $delivery = Mockery::mock(DeliverOutboxEvent::class);
        $delivery->shouldReceive('execute')->once()->with(10, Mockery::type('string'));

        (new DeliverCommunication(10, '00000000-0000-4000-8000-000000000001'))->handle($delivery);

        $summary = app(OperationalMetrics::class)->jobSummary();
        $this->assertSame(1, $summary['count']);
        $this->assertSame(0, $summary['errors']);
        Log::shouldHaveReceived('log')->once()->with('info', 'communication.job.outcome', Mockery::on(fn (array $context): bool => $context['outbox_id'] === 10
            && $context['correlation_id'] === '00000000-0000-4000-8000-000000000001'
            && $context['outcome'] === 'completed'));
    }

    public function test_communication_job_does_not_rethrow_provider_details_to_the_queue_backend(): void
    {
        Log::spy();
        $delivery = Mockery::mock(DeliverOutboxEvent::class);
        $delivery->shouldReceive('execute')->once()->andThrow(new RuntimeException('smtp://mail.example.test recipient@example.test password=never-log'));

        try {
            (new DeliverCommunication(11, '00000000-0000-4000-8000-000000000002'))->handle($delivery);
            $this->fail('A entrega deveria ser rejeitada.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Communication delivery failed; correlation_id=00000000-0000-4000-8000-000000000002', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        $summary = app(OperationalMetrics::class)->jobSummary();
        $this->assertSame(1, $summary['count']);
        $this->assertSame(1, $summary['errors']);
        Log::shouldHaveReceived('warning')->with('communication.job.failed_attempt', Mockery::on(fn (array $context): bool => ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'never-log')));
    }
}
