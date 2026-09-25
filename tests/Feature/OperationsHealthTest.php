<?php

namespace Tests\Feature;

use App\Enums\OutboxStatus;
use App\Enums\OutboxType;
use App\Models\CommunicationOutbox;
use App\Models\User;
use App\Services\Operations\OperationsHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('catalog.store', 'array');
    }

    public function test_only_an_active_admin_can_view_the_operations_health_route(): void
    {
        $this->get(route('operacao.saude'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->reader()->create())->get(route('operacao.saude'))->assertForbidden();
        $this->actingAs(User::factory()->librarian()->create())->get(route('operacao.saude'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->inactive()->create())->get(route('operacao.saude'))->assertRedirect(route('login'));
    }

    public function test_degraded_json_is_safe_when_the_redis_queue_is_unavailable(): void
    {
        Queue::shouldReceive('connection')->once()->with('redis')->andThrow(new RuntimeException('redis://secret-host:6379 unavailable'));
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('operacao.saude'))
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('dependencies.redis_queue.healthy', false)
            ->assertJsonMissing(['message' => 'redis://secret-host:6379 unavailable'])
            ->assertDontSee('secret-host');
    }

    public function test_stale_scheduler_heartbeat_is_reported_after_three_minutes(): void
    {
        Cache::put(OperationsHealth::HEARTBEAT_KEY, now()->subSeconds(181)->getTimestamp(), now()->addMinutes(10));

        $scheduler = app(OperationsHealth::class)->schedulerReport();

        $this->assertFalse($scheduler['healthy']);
        $this->assertSame(181, $scheduler['seconds_ago']);
    }

    public function test_heartbeat_command_marks_the_scheduler_as_current(): void
    {
        $this->artisan('operacoes:heartbeat')->assertExitCode(0);

        $scheduler = app(OperationsHealth::class)->schedulerReport();

        $this->assertTrue($scheduler['healthy']);
        $this->assertSame(0, $scheduler['seconds_ago']);
    }

    public function test_outbox_faults_are_reported_with_actionable_alerts_and_valid_retry_wait_is_not_overdue(): void
    {
        $queue = Mockery::mock();
        $queue->shouldReceive('size')->once()->with('communications')->andReturn(0);
        Queue::shouldReceive('connection')->once()->with('redis')->andReturn($queue);
        Cache::put(OperationsHealth::HEARTBEAT_KEY, now()->getTimestamp(), now()->addMinutes(10));
        $reader = User::factory()->reader()->create();

        $this->outbox($reader, OutboxStatus::Failed, now()->subSeconds(121));
        $this->outbox($reader, OutboxStatus::Pending, now()->subSeconds(121));
        $this->outbox($reader, OutboxStatus::Pending, now()->subSeconds(121), now()->addSeconds(30));
        $this->outbox($reader, OutboxStatus::Processing, now()->subSeconds(121), null, now()->subSeconds(121));

        $report = app(OperationsHealth::class)->report();

        $this->assertFalse($report['healthy']);
        $this->assertContains('outbox_failed', $report['alerts']);
        $this->assertContains('outbox_pending_overdue', $report['alerts']);
        $this->assertContains('outbox_processing_stale', $report['alerts']);
        $this->assertGreaterThan(0, $report['outbox']['oldest_pending_seconds']);
        $this->assertSame(1, $report['outbox']['actionable_pending_count']);
    }

    private function outbox(User $reader, OutboxStatus $status, \DateTimeInterface $createdAt, ?\DateTimeInterface $nextAttemptAt = null, ?\DateTimeInterface $leasedAt = null): void
    {
        $event = new CommunicationOutbox;
        $event->forceFill([
            'event_key' => 'operations-health:'.uniqid('', true),
            'type' => OutboxType::LoanDueSoon,
            'usuario_id' => $reader->id,
            'payload' => [],
            'status' => $status,
            'correlation_id' => (string) Str::uuid(),
            'next_attempt_at' => $nextAttemptAt,
            'leased_at' => $leasedAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $event->save();
    }
}
