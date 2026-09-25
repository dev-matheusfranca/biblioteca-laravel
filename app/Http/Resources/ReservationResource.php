<?php

namespace App\Http\Resources;

use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reserva */
class ReservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'livro' => new LivroResource($this->whenLoaded('livro')),
            'exemplar' => $this->whenLoaded('exemplar', fn () => $this->exemplar ? [
                'id' => $this->exemplar->id,
                'codigo_patrimonial' => $this->exemplar->codigo_patrimonial,
            ] : null),
            'disponivel_em' => $this->disponivel_em?->toIso8601String(),
            'retirar_ate' => $this->expira_em?->toIso8601String(),
            'encerrada_em' => $this->encerrada_em?->toIso8601String(),
            'motivo_encerramento' => $this->encerramento_motivo,
            'criada_em' => $this->created_at?->toIso8601String(),
        ];
    }
}
