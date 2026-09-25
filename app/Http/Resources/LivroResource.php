<?php

namespace App\Http\Resources;

use App\Models\Livro;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Livro */
class LivroResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'isbn' => $this->isbn,
            'ano_publicacao' => $this->ano_publicacao,
            'autor' => $this->whenLoaded('autor', fn () => [
                'id' => $this->autor?->id,
                'nome' => $this->autor?->nome,
            ]),
            'categoria' => $this->whenLoaded('categoria', fn () => [
                'id' => $this->categoria?->id,
                'nome' => $this->categoria?->nome,
            ]),
            'disponibilidade' => [
                'modo' => $this->modo_acervo->value,
                'total' => $this->quantidade_total,
                'disponiveis' => $this->quantidade_disponivel,
            ],
        ];
    }
}
