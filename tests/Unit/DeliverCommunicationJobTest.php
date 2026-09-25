<?php

namespace Tests\Unit;

use App\Jobs\DeliverCommunication;
use PHPUnit\Framework\TestCase;

class DeliverCommunicationJobTest extends TestCase
{
    public function test_job_retry_contract_matches_communications_worker(): void
    {
        $job = new DeliverCommunication(10, '00000000-0000-4000-8000-000000000001');

        $this->assertSame(3, $job->tries);
        $this->assertSame(20, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame([5, 30, 120], $job->backoff());
        $this->assertSame('communications', $job->queue);
    }
}
