<?php

namespace App\Policies;

use App\Models\Locacao;
use App\Models\User;

class LocacaoPolicy
{
    public function view(User $user, Locacao $locacao): bool
    {
        return $user->isActive() && $locacao->usuario_id === $user->getKey();
    }
}
