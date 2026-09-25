<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoCanary extends Command
{
    protected $signature = 'demo:canary';

    protected $description = 'Verifica contagens e integridade da demonstração local sem alterar dados.';

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();
        if (! config('demo.enabled') || ! preg_match('/^biblioteca_demo(?:_restore_[0-9]+)?$/', $database)) {
            $this->error('Canário restrito ao banco da demonstração local.');

            return self::FAILURE;
        }
        $counts = [];
        foreach (['users', 'livros', 'exemplares', 'locacoes', 'reservas'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $invalid = DB::table('locacoes')
            ->leftJoin('exemplares', 'exemplares.id', '=', 'locacoes.exemplar_id')
            ->whereNull('locacoes.encerrado_em')
            ->where(fn ($query) => $query->whereNull('locacoes.exemplar_id')
                ->orWhereNull('locacoes.active_exemplar_id')
                ->orWhereNull('exemplares.id')
                ->orWhereColumn('locacoes.active_exemplar_id', '!=', 'locacoes.exemplar_id')
                ->orWhereColumn('locacoes.livro_id', '!=', 'exemplares.livro_id'))
            ->count();
        $migrations = glob(database_path('migrations/*.php')) ?: [];
        sort($migrations);
        $signature = hash('sha256', implode('', array_map(fn ($file) => basename($file).hash_file('sha256', $file), $migrations)));
        $this->line(json_encode(['counts' => $counts, 'invalid_open_loans' => $invalid, 'schema_signature' => $signature], JSON_THROW_ON_ERROR));

        return $invalid === 0 ? self::SUCCESS : self::FAILURE;
    }
}
