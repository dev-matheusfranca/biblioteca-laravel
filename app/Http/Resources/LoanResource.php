<?php

namespace App\Http\Resources;

use App\Models\Locacao;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Locacao */
class LoanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->situacao_atual,
            'data_emprestimo' => $this->data_locacao,
            'data_prevista_devolucao' => $this->data_devolucao,
            'data_devolvida' => $this->data_devolvido,
            'encerrado_em' => $this->encerrado_em ? CarbonImmutable::parse($this->encerrado_em)->toIso8601String() : null,
            'renovacoes_utilizadas' => $this->renewal_count,
            'livro' => new LivroResource($this->whenLoaded('livro')),
            'renovacoes' => $this->whenLoaded('renovacoes', fn () => $this->renovacoes->map(fn ($renewal) => [
                'id' => $renewal->id,
                'prazo_anterior' => $renewal->previous_due_date,
                'novo_prazo' => $renewal->new_due_date,
                'criada_em' => $renewal->created_at?->toIso8601String(),
            ])),
        ];
    }
}
