<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_librarian_creates_reader_without_accepting_role_escalation(): void
    {
        $librarian = User::factory()->librarian()->create();

        $this->actingAs($librarian)->post(route('leitores.store'), [
            'name' => 'Novo Leitor',
            'email' => 'novo.leitor@example.test',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'role' => 'admin',
            'is_active' => '1',
        ])->assertRedirect(route('leitores.index'));

        $reader = User::query()->where('email', 'novo.leitor@example.test')->firstOrFail();
        $this->assertSame('leitor', $reader->role->value);
        $this->assertTrue($reader->isActive());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reader.created',
            'actor_id' => $librarian->id,
            'subject_id' => $reader->id,
        ]);
    }

    public function test_librarian_cannot_edit_a_staff_member_through_reader_routes(): void
    {
        $librarian = User::factory()->librarian()->create();
        $staff = User::factory()->admin()->create();

        $this->actingAs($librarian)->get(route('leitores.edit', $staff))->assertNotFound();
        $this->actingAs($librarian)->put(route('leitores.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'is_active' => '0',
        ])->assertNotFound();

        $this->assertTrue($staff->fresh()->isActive());
    }

    public function test_admin_role_change_is_audited_without_sensitive_payload(): void
    {
        $admin = User::factory()->admin()->create();
        $reader = User::factory()->reader()->create();

        $this->actingAs($admin)->patch(route('equipe.update', $reader), [
            'role' => 'bibliotecario',
            'is_active' => '1',
            'password' => 'must-not-be-stored',
        ])->assertSessionHas('success');

        $reader->refresh();
        $this->assertSame('bibliotecario', $reader->role->value);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'team_member.updated',
            'actor_id' => $admin->id,
            'subject_id' => $reader->id,
        ]);
        $audit = AuditLog::query()->where('action', 'team_member.updated')->firstOrFail();
        $this->assertIsArray($audit->metadata);
        $this->assertArrayNotHasKey('password', $audit->metadata);
        $this->assertArrayNotHasKey('email', $audit->metadata);
    }

    public function test_admin_cannot_demote_or_deactivate_the_last_active_admin_or_themself(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('equipe.update', $admin), [
            'role' => 'bibliotecario',
            'is_active' => '1',
        ])->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role->value);

        $otherAdmin = User::factory()->admin()->create();
        $this->actingAs($otherAdmin)->patch(route('equipe.update', $admin), [
            'role' => 'bibliotecario',
            'is_active' => '1',
        ])->assertSessionHas('success');
        $this->assertSame('bibliotecario', $admin->fresh()->role->value);

        $this->actingAs($otherAdmin)->patch(route('equipe.update', $otherAdmin), [
            'role' => 'admin',
            'is_active' => '0',
        ])->assertSessionHasErrors('role');
        $this->assertTrue($otherAdmin->fresh()->isActive());
    }

    public function test_bootstrap_command_promotes_an_existing_user_and_records_audit_entry(): void
    {
        $reader = User::factory()->reader()->create();

        $this->artisan('biblioteca:bootstrap-admin', ['user' => (string) $reader->id])
            ->expectsOutput('Administrador provisionado.')
            ->assertExitCode(0);

        $this->assertSame('admin', $reader->fresh()->role->value);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'administrator.bootstrapped',
            'actor_id' => null,
            'subject_id' => $reader->id,
        ]);
    }
}
