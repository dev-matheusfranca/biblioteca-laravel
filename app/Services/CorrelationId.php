<?php

namespace App\Services;

use Illuminate\Support\Str;

class CorrelationId
{
    public function current(): string
    {
        if (app()->bound('request')) {
            $requestId = request()->attributes->get('request_id');
            if (is_string($requestId) && Str::isUuid($requestId)) {
                return $requestId;
            }
        }

        return (string) Str::uuid();
    }
}
