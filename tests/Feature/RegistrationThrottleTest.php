<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_limited_per_ip_address(): void
    {
        $server = ['REMOTE_ADDR' => '203.0.113.10'];

        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables($server)->post(route('register.post'), [
                'nome' => "Leitor {$attempt}",
                'email' => "leitor{$attempt}@example.test",
                'password' => 'senha-segura',
                'password_confirmation' => 'senha-segura',
            ])->assertRedirect(route('portal.index'));
        }

        $this->withServerVariables($server)->post(route('register.post'), [
            'nome' => 'Leitor excedente',
            'email' => 'leitor-excedente@example.test',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
        ])->assertTooManyRequests();

        $this->assertSame(5, User::query()->count());
    }
}
