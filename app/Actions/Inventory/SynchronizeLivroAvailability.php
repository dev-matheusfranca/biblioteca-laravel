<?php

namespace App\Actions\Inventory;

use App\Enums\ExemplarCondition;
use App\Models\Exemplar;
use App\Models\Livro;

class SynchronizeLivroAvailability
{
    public function execute(Livro $livro): void
    {
        $total = Exemplar::query()->where('livro_id', $livro->getKey())->count();
        $available = $livro->usaExemplares()
            ? Exemplar::query()
                ->where('livro_id', $livro->getKey())
                ->where('condicao', ExemplarCondition::Circulation->value)
                ->where('identificacao_fisica', true)
                ->whereDoesntHave('locacaoAtiva')
                ->whereDoesntHave('reservaAtiva')
                ->count()
            : 0;

        $livro->forceFill([
            'quantidade_total' => $total,
            'quantidade_disponivel' => $available,
        ])->save();
    }
}
