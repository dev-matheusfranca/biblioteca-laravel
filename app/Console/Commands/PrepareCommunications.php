<?php

namespace App\Console\Commands;

use App\Actions\Communication\PrepareCommunicationEvents;
use Illuminate\Console\Command;

class PrepareCommunications extends Command
{
    protected $signature = 'comunicacoes:preparar';

    protected $description = 'Prepara lembretes de circulação na caixa de saída durável';

    public function handle(PrepareCommunicationEvents $prepare): int
    {
        $this->components->info($prepare->execute().' evento(s) novo(s) preparado(s).');

        return self::SUCCESS;
    }
}
