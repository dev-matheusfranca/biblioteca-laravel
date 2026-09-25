<?php

namespace App\Support;

final class PersonalTokenAbilities
{
    /** @var array<string, string> */
    public const ALL = [
        'personal:read' => 'Consultar meus dados, empréstimos e reservas',
        'reservations:write' => 'Criar e cancelar minhas reservas',
        'loans:renew' => 'Renovar meus empréstimos',
    ];
}
