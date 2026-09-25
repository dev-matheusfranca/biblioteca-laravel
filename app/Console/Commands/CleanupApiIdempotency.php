<?php

namespace App\Console\Commands;

use App\Models\ApiIdempotencyRecord;
use Illuminate\Console\Command;

class CleanupApiIdempotency extends Command
{
    protected $signature = 'api:idempotency-clean';

    protected $description = 'Remove respostas de idempotência expiradas da API';

    public function handle(): int
    {
        $deleted = ApiIdempotencyRecord::query()->where('expires_at', '<=', now())->delete();
        $this->components->info($deleted.' registro(s) expirado(s) removido(s).');

        return self::SUCCESS;
    }
}
