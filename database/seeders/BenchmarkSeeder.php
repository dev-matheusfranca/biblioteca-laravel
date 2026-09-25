<?php

namespace Database\Seeders;

use App\Services\Catalog\PublicCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class BenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || DB::connection()->getDriverName() !== 'mysql'
            || ! preg_match('/^biblioteca_benchmark_[0-9]+$/', DB::connection()->getDatabaseName())
            || DB::table('livros')->exists() || DB::table('users')->exists()) {
            throw new RuntimeException('Use somente um novo banco MySQL biblioteca_benchmark_<timestamp>, vazio e local.');
        }

        DB::transaction(function () {
            $timestamp = '2026-09-01 10:00:00';
            $password = Hash::make(bin2hex(random_bytes(32)));
            for ($id = 1; $id <= 200; $id++) {
                DB::table('autores')->insert(['id' => $id, 'nome' => sprintf('Autor sintético %03d', $id), 'created_at' => $timestamp, 'updated_at' => $timestamp]);
                DB::table('users')->insert(['id' => $id, 'name' => sprintf('Leitor sintético %03d', $id), 'email' => "bench{$id}@example.test", 'password' => $password, 'role' => 'leitor', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
            }
            for ($id = 1; $id <= 20; $id++) {
                DB::table('categorias')->insert(['id' => $id, 'nome' => sprintf('Categoria %02d', $id), 'created_at' => $timestamp, 'updated_at' => $timestamp]);
            }
            $books = $copies = [];
            for ($id = 1; $id <= 5000; $id++) {
                $maintenance = $id % 10 === 0;
                $books[] = ['id' => $id, 'titulo' => sprintf('Acervo sintético %05d', $id), 'autor_id' => ($id - 1) % 200 + 1, 'categoria_id' => ($id - 1) % 20 + 1, 'quantidade_total' => 3, 'quantidade_disponivel' => $maintenance ? 2 : 3, 'status' => 'ativo', 'modo_acervo' => 'exemplares', 'created_at' => $timestamp, 'updated_at' => $timestamp];
                for ($unit = 1; $unit <= 3; $unit++) {
                    $copies[] = ['id' => ($id - 1) * 3 + $unit, 'livro_id' => $id, 'codigo_patrimonial' => "BENCH-{$id}-{$unit}", 'condicao' => $maintenance && $unit === 3 ? 'manutencao' : 'circulacao', 'identificacao_fisica' => true, 'origem' => 'benchmark-sintetico', 'created_at' => $timestamp, 'updated_at' => $timestamp];
                }
                if ($id % 100 === 0) {
                    DB::table('livros')->insert($books);
                    DB::table('exemplares')->insert($copies);
                    $books = $copies = [];
                }
            }
            $loans = [];
            for ($id = 0; $id < 20000; $id++) {
                $book = $id % 5000 + 1;
                $start = CarbonImmutable::parse('2023-01-01')->addDays(intdiv($id, 200) * 7);
                $closed = $start->addDays(5);
                $loans[] = ['usuario_id' => $id % 200 + 1, 'livro_id' => $book, 'exemplar_id' => ($book - 1) * 3 + 1, 'data_locacao' => $start->toDateString(), 'data_devolucao' => $start->addDays(14)->toDateString(), 'data_devolvido' => $closed->toDateString(), 'encerrado_em' => $closed->setTime(12, 0)->toDateTimeString(), 'encerramento_motivo' => 'devolucao', 'status' => 'devolvida', 'created_at' => $start->toDateTimeString(), 'updated_at' => $closed->toDateTimeString()];
                if (count($loans) === 500) {
                    DB::table('locacoes')->insert($loans);
                    $loans = [];
                }
            }
        });
        app(PublicCatalog::class)->invalidate();
        $this->command?->info('Dataset sintético: 5000 títulos, 15000 exemplares, 200 leitores, 20000 empréstimos encerrados.');
    }
}
