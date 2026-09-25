<?php

namespace App\Http\Controllers;

use App\Actions\Circulation\AllocateReservationsForBook;
use App\Enums\ExemplarCondition;
use App\Models\AuditLog;
use App\Models\Exemplar;
use App\Models\Livro;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExemplarController extends Controller
{
    public function index(Livro $livro)
    {
        return view('exemplares.index', ['livro' => $livro, 'exemplares' => $livro->exemplares()->with('locacaoAtiva')->paginate(15)]);
    }

    public function store(Request $request, Livro $livro)
    {
        $data = $request->validate(['codigo_patrimonial' => ['required', 'string', 'max:100', 'unique:exemplares,codigo_patrimonial'], 'condicao' => ['required', Rule::enum(ExemplarCondition::class)], 'motivo' => ['required', 'string', 'max:1000']]);
        if (! $livro->usaExemplares()) {
            return back()->withErrors(['codigo_patrimonial' => 'Conclua a reconciliação antes de cadastrar unidades.']);
        }
        if ($data['condicao'] !== ExemplarCondition::Circulation->value && ! $data['motivo']) {
            return back()->withErrors(['motivo' => 'Informe o motivo da condição.']);
        }
        try {
            DB::transaction(function () use ($data, $livro, $request) {
                $locked = Livro::query()->lockForUpdate()->findOrFail($livro->id);
                Exemplar::create(['livro_id' => $locked->id, 'codigo_patrimonial' => $data['codigo_patrimonial'], 'condicao' => $data['condicao'], 'origem' => 'cadastro_equipe', 'identificacao_fisica' => true, 'motivo_condicao' => $data['motivo']]);
                app(AllocateReservationsForBook::class)->executeLocked($locked);
                AuditLog::create(['action' => 'copy.created', 'actor_id' => $request->user()->id, 'metadata' => ['livro_id' => $locked->id]]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['codigo_patrimonial' => 'Este código patrimonial já foi cadastrado.']);
        }

        return back()->with('success', 'Exemplar cadastrado.');
    }

    public function updateCondition(Request $request, Exemplar $exemplar)
    {
        $data = $request->validate(['condicao' => ['required', Rule::enum(ExemplarCondition::class)], 'motivo' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($data, $exemplar, $request) {
            $book = Livro::query()->lockForUpdate()->findOrFail($exemplar->livro_id);
            $copy = Exemplar::query()->lockForUpdate()->findOrFail($exemplar->id);
            if ($copy->locacaoAtiva()->exists() || $copy->reservaAtiva()->exists()) {
                throw ValidationException::withMessages(['condicao' => 'Não é possível alterar a condição de um exemplar emprestado ou separado para reserva.']);
            }
            $copy->forceFill(['condicao' => $data['condicao'], 'motivo_condicao' => $data['motivo']])->save();
            app(AllocateReservationsForBook::class)->executeLocked($book);
            AuditLog::create(['action' => 'copy.condition_changed', 'actor_id' => $request->user()->id, 'metadata' => ['livro_id' => $book->id, 'exemplar_id' => $copy->id]]);
        }, 3);

        return back()->with('success', 'Condição do exemplar atualizada.');
    }
}
