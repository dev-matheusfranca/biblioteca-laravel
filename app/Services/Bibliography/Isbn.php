<?php

namespace App\Services\Bibliography;

final class Isbn
{
    public static function normalize(string $value): ?string
    {
        $isbn = strtoupper(preg_replace('/[\s-]+/', '', trim($value)) ?? '');
        if (preg_match('/^\d{9}[\dX]$/D', $isbn)) {
            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $sum += ($isbn[$i] === 'X' ? 10 : (int) $isbn[$i]) * (10 - $i);
            }

            return $sum % 11 === 0 ? $isbn : null;
        }
        if (preg_match('/^97[89]\d{10}$/D', $isbn)) {
            $sum = 0;
            for ($i = 0; $i < 13; $i++) {
                $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
            }

            return $sum % 10 === 0 ? $isbn : null;
        }

        return null;
    }
}
