<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Waiting = 'aguardando';
    case Available = 'disponivel';
    case Fulfilled = 'atendida';
    case Cancelled = 'cancelada';
    case Expired = 'expirada';

    public function isActive(): bool
    {
        return in_array($this, [self::Waiting, self::Available], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Aguardando exemplar',
            self::Available => 'Disponível para retirada',
            self::Fulfilled => 'Atendida',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Prazo expirado',
        };
    }
}
