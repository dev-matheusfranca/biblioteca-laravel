<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\SynchronizeLivroAvailability;
use App\Enums\AcervoMode;
use App\Models\AuditLog;
use App\Models\Exemplar;
use App\Models\Livro;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconciliacaoLivroController extends Controller
{
    public function edit(Livro $livro)
    {
        $locacoesAbertas = $livro->locacoes()->whereNull('encerrado_em')->with('usuario')->get();
        $discrepancias = ['total_legacy' => $livro->quantidade_total, 'total_informado' => 0, 'abertas_legacy' => $locacoesAbertas->count(), 'vinculacoes_informadas' => 0, 'disponiveis_legacy' => $livro->quantidade_disponivel, 'disponiveis_reconciliados' => 0, 'ha_divergencia' => false];

        return view('livros.reconciliacao', compact('livro', 'locacoesAbertas', 'discrepancias'));
    }

    public function store(Request $request, Livro $livro)
    {
        $data = $request->validate([
            'unidades' => ['required', 'array', 'min:1', 'max:100'],
            'unidades.*.codigo_patrimonial' => ['required', 'string', 'distinct', 'max:64', 'unique:exemplares,codigo_patrimonial'],
            'unidades.*.condicao' => ['required', 'in:circulacao,manutencao,extraviado,baixado'],
            'unidades.*.identificacao_fisica' => ['required', 'accepted'],
            'vinculacoes' => ['array'], 'vinculacoes.*' => ['required', 'string', 'distinct', 'max:64'],
            'confirmar_divergencia' => ['nullable', 'boolean'],
            'motivo' => ['required', 'string', 'max:1000'],
        ]);
        try {
            DB::transaction(function () use ($data, $livro, $request) {
                $book = Livro::query()->lockForUpdate()->findOrFail($livro->id);
                if ($book->usaExemplares()) {
                    throw ValidationException::withMessages(['unidades' => 'Título já reconciliado.']);
                }
                $loans = $book->locacoes()->whereNull('encerrado_em')->orderBy('id')->lockForUpdate()->get();
                $mappings = $data['vinculacoes'] ?? [];
                $units = collect($data['unidades'])->keyBy('codigo_patrimonial');
                if (count($mappings) !== $loans->count() || count(array_unique($mappings)) !== count($mappings)) {
                    throw ValidationException::withMessages(['vinculacoes' => 'Vincule cada empréstimo aberto a uma unidade diferente.']);
                }
                foreach ($loans as $loan) {
                    $code = $mappings[$loan->id] ?? null;
                    if (! isset($units[$code]) || $units[$code]['condicao'] !== 'circulacao') {
                        throw ValidationException::withMessages(['vinculacoes' => 'Cada empréstimo aberto exige uma unidade identificada em circulação. Registre perdas após a conferência.']);
                    }
                }
                $available = $units->where('condicao', 'circulacao')->count() - $loans->count();
                $before = ['total' => $book->quantidade_total, 'disponiveis' => $book->quantidade_disponivel, 'abertos' => $loans->count()];
                if (($units->count() !== $before['total'] || $available !== $before['disponiveis']) && ! $request->boolean('confirmar_divergencia')) {
                    throw ValidationException::withMessages(['confirmar_divergencia' => 'Confirme a divergência do inventário e registre o motivo.']);
                }
                $copies = [];
                foreach ($units as $unit) {
                    $copy = Exemplar::create([
                        'livro_id' => $book->id, 'codigo_patrimonial' => $unit['codigo_patrimonial'],
                        'condicao' => $unit['condicao'], 'origem' => 'reconciliacao',
                        'identificacao_fisica' => true, 'motivo_condicao' => $data['motivo'],
                    ]);
                    $copies[$copy->codigo_patrimonial] = $copy;
                }
                foreach ($loans as $loan) {
                    $copy = $copies[$mappings[$loan->id]];
                    $loan->forceFill(['exemplar_id' => $copy->id, 'active_exemplar_id' => $copy->id])->save();
                }
                $book->forceFill(['modo_acervo' => AcervoMode::Copies])->save();
                app(SynchronizeLivroAvailability::class)->execute($book);
                AuditLog::create(['action' => 'book.reconciled', 'actor_id' => $request->user()->id,
                    'metadata' => ['livro_id' => $book->id, 'antes' => $before, 'total' => $units->count(), 'disponiveis' => $available, 'motivo' => $data['motivo']]]);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['unidades' => 'Um código patrimonial já foi cadastrado. Confira as unidades.']);
        }

        return redirect()->route('exemplares.index', $livro)->with('success', 'Inventário reconciliado.');
    }
}
