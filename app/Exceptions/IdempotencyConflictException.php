<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A Idempotency-Key informada já foi usada em outra operação.');
    }
}
