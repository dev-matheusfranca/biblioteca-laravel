<?php

namespace Tests\Feature;

use App\Enums\OutboxStatus;
use App\Enums\OutboxType;
use App\Models\CommunicationOutbox;
use App\Models\PortalNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalNoticeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_can_read_own_notice_but_other_reader_gets_not_found(): void
    {
        $owner = User::factory()->reader()->create();
        $other = User::factory()->reader()->create();
        $notice = $this->notice($owner);

        $this->actingAs($other)->patch(route('avisos.read', $notice))->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);

        $this->actingAs($owner)->patch(route('avisos.read', $notice))->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
    }

    public function test_only_admin_can_view_operations_and_replay_failed_event(): void
    {
        $reader = User::factory()->reader()->create();
        $librarian = User::factory()->librarian()->create();
        $event = $this->event($reader, OutboxStatus::Failed);

        $this->actingAs($librarian)->get(route('operacao.comunicacoes.index'))->assertForbidden();
        $this->actingAs($librarian)->post(route('operacao.comunicacoes.reprocessar', $event))->assertForbidden();
    }

    private function notice(User $owner): PortalNotice
    {
        $event = $this->event($owner, OutboxStatus::Sent);

        return PortalNotice::create([
            'outbox_id' => $event->id,
            'usuario_id' => $owner->id,
            'type' => OutboxType::LoanDueSoon->value,
            'title' => 'Aviso',
            'body' => 'Mensagem',
        ]);
    }

    private function event(User $owner, OutboxStatus $status): CommunicationOutbox
    {
        return CommunicationOutbox::create([
            'event_key' => 'test:'.Str::uuid(),
            'type' => OutboxType::LoanDueSoon,
            'usuario_id' => $owner->id,
            'payload' => ['loan_id' => 1, 'book_id' => 1, 'user_id' => $owner->id, 'due_date' => '2026-09-27'],
            'status' => $status,
            'correlation_id' => (string) Str::uuid(),
        ]);
    }
}
