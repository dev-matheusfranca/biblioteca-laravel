<?php

namespace App\Console\Commands;

use App\Actions\Communication\PublishOutbox;
use Illuminate\Console\Command;

class PublishOutboxEvents extends Command
{
    protected $signature = 'outbox:publicar {--limit=100}';

    protected $description = 'Publica eventos pendentes da caixa de saída na fila de comunicações';

    public function handle(PublishOutbox $publish): int
    {
        $limit = min(1000, max(1, (int) $this->option('limit')));
        $this->components->info($publish->execute($limit).' evento(s) publicado(s).');

        return self::SUCCESS;
    }
}
