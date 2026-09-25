<?php

namespace Tests\Support;

use App\Actions\Inventory\SynchronizeLivroAvailability;
use App\Enums\ExemplarCondition;
use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Exemplar;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\User;

final class PhysicalCatalog
{
    /** @return array{Autor, Categoria, Livro, array<int, Exemplar>} */
    public static function book(array $attributes = [], int $copies = 1, ?Autor $author = null, ?Categoria $category = null): array
    {
        $author ??= Autor::create(['nome' => 'Autor Exemplo', 'nacionalidade' => 'Brasileira']);
        $category ??= Categoria::create(['nome' => 'Tecnologia']);
        $copies = (int) ($attributes['quantidade_total'] ?? $copies);
        $availableCopies = min($copies, max(0, (int) ($attributes['quantidade_disponivel'] ?? $copies)));

        $book = Livro::create(array_merge([
            'titulo' => 'Livro Exemplo',
            'autor_id' => $author->id,
            'categoria_id' => $category->id,
            'quantidade_total' => 0,
            'quantidade_disponivel' => 0,
            'status' => 'ativo',
            'modo_acervo' => 'exemplares',
        ], $attributes));

        $copies = collect($copies > 0 ? range(1, $copies) : [])
            ->map(fn (int $number): Exemplar => Exemplar::create([
                'livro_id' => $book->id,
                'codigo_patrimonial' => sprintf('TEST-%d-%03d', $book->id, $number),
                'condicao' => $number <= $availableCopies
                    ? ExemplarCondition::Circulation
                    : ExemplarCondition::Maintenance,
                'origem' => 'teste',
                'identificacao_fisica' => true,
                'motivo_condicao' => $number <= $availableCopies ? null : 'Indisponível no cenário de teste.',
            ]))
            ->all();

        app(SynchronizeLivroAvailability::class)->execute($book);
        $book->refresh();

        return [$author, $category, $book, $copies];
    }

    public static function loan(User $reader, Livro $book, array $attributes = []): Locacao
    {
        $isClosed = ($attributes['status'] ?? 'ativa') === 'devolvida';
        $copy = $book->exemplares()
            ->where('condicao', ExemplarCondition::Circulation->value)
            ->when(! $isClosed, fn ($query) => $query->whereDoesntHave('locacaoAtiva'))
            ->orderBy('id')
            ->firstOrFail();

        $loan = Locacao::create(array_merge([
            'usuario_id' => $reader->id,
            'livro_id' => $book->id,
            'exemplar_id' => $copy->id,
            'active_exemplar_id' => $isClosed ? null : $copy->id,
            'data_locacao' => now()->toDateString(),
            'data_devolucao' => now()->addDay()->toDateString(),
            'status' => 'ativa',
        ], $attributes));

        if ($isClosed && ! array_key_exists('encerrado_em', $attributes)) {
            $loan->forceFill(['encerrado_em' => now(), 'encerramento_motivo' => 'devolucao'])->save();
        }

        app(SynchronizeLivroAvailability::class)->execute($book);

        return $loan;
    }
}
