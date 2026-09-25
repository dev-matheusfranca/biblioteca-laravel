<?php

namespace App\Services;

use App\Models\CirculationPolicy;
use DomainException;

class CurrentCirculationPolicy
{
    public function get(bool $lockForUpdate = false): CirculationPolicy
    {
        $query = CirculationPolicy::query()->where('active_key', 'current');
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first() ?? throw new DomainException('A política de circulação ativa não foi configurada.');
    }
}
