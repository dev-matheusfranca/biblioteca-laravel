<?php

namespace App\Services\Reports;

use App\Models\Livro;
use App\Models\Locacao;
use App\Models\Reserva;
use Illuminate\Database\Eloquent\Builder;

class CirculationReport
{
    public const POPULATION_LABELS = [
        'period' => 'Empréstimos iniciados no período',
        'open' => 'Empréstimos abertos na referência',
        'overdue' => 'Empréstimos atrasados na referência',
    ];

    /** @return array{checkouts:int,returns:int,losses:int,reservation_requests:int,open_at_reference:int,overdue_at_reference:int} */
    public function summary(ReportPeriod $period, ?string $search = null): array
    {
        $openAtReference = $this->openAtReferenceQuery($period, $search);

        return [
            'checkouts' => $this->loanBookSearch(Locacao::query(), $search)
                ->whereBetween('data_locacao', [$period->startDate(), $period->endDate()])
                ->count(),
            'returns' => $this->loanBookSearch(Locacao::query(), $search)
                ->where('encerramento_motivo', 'devolucao')
                ->whereBetween('data_devolvido', [$period->startDate(), $period->endDate()])
                ->count(),
            'losses' => $this->loanBookSearch(Locacao::query(), $search)
                ->where('encerramento_motivo', 'perda')
                ->whereBetween('encerrado_em', [$period->periodStart(), $period->periodEnd()])
                ->count(),
            'reservation_requests' => Reserva::query()
                ->when($search, fn (Builder $query, string $term) => $query
                    ->whereHas('livro', fn (Builder $books) => $books->whereLike('titulo', "%{$term}%")))
                ->whereBetween('created_at', [$period->periodStart(), $period->periodEnd()])
                ->count(),
            'open_at_reference' => (clone $openAtReference)->count(),
            'overdue_at_reference' => $this->overdueAtReferenceQuery($period, $search)->count(),
        ];
    }

    /** @return Builder<Livro> */
    public function demandQuery(ReportPeriod $period, ?string $search = null): Builder
    {
        $checkouts = Locacao::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('locacoes.livro_id', 'livros.id')
            ->whereBetween('locacoes.data_locacao', [$period->startDate(), $period->endDate()]);
        $reservations = Reserva::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('reservas.livro_id', 'livros.id')
            ->whereBetween('reservas.created_at', [$period->periodStart(), $period->periodEnd()]);

        return Livro::query()
            ->select(['livros.id', 'livros.titulo'])
            ->selectSub($checkouts, 'emprestimos_periodo')
            ->selectSub($reservations, 'reservas_periodo')
            ->when($search, fn (Builder $query, string $term) => $query->whereLike('livros.titulo', "%{$term}%"))
            ->where(function (Builder $query) use ($period): void {
                $query->whereHas('locacoes', fn (Builder $loans) => $loans
                    ->whereBetween('data_locacao', [$period->startDate(), $period->endDate()]))
                    ->orWhereHas('reservas', fn (Builder $reservations) => $reservations
                        ->whereBetween('created_at', [$period->periodStart(), $period->periodEnd()]));
            })
            ->orderByDesc('emprestimos_periodo')
            ->orderByDesc('reservas_periodo')
            ->orderBy('livros.titulo')
            ->orderBy('livros.id');
    }

    /** @return Builder<Locacao> */
    public function openAtReferenceQuery(ReportPeriod $period, ?string $search = null): Builder
    {
        return $this->loanBookSearch(Locacao::query(), $search)
            ->where('data_locacao', '<=', $period->referenceDate())
            ->where(function (Builder $query) use ($period): void {
                $query->whereNull('encerrado_em')
                    ->orWhere('encerrado_em', '>', $period->referenceEnd());
            });
    }

    /** @return Builder<Locacao> */
    public function overdueAtReferenceQuery(ReportPeriod $period, ?string $search = null): Builder
    {
        return $this->openAtReferenceQuery($period, $search)
            ->whereRaw($this->dueAtReferenceSql().' < ?', [
                $period->referenceEnd(),
                $period->referenceDate(),
            ]);
    }

    /** @return Builder<Locacao> */
    public function loansQuery(ReportPeriod $period, string $population, ?string $search = null): Builder
    {
        $query = match ($population) {
            'open' => $this->openAtReferenceQuery($period),
            'overdue' => $this->overdueAtReferenceQuery($period),
            default => Locacao::query()
                ->whereBetween('locacoes.data_locacao', [$period->startDate(), $period->endDate()]),
        };

        $query
            ->join('livros', 'livros.id', '=', 'locacoes.livro_id')
            ->select([
                'locacoes.id',
                'locacoes.livro_id',
                'locacoes.data_locacao',
                'locacoes.data_devolucao',
                'locacoes.encerrado_em',
                'locacoes.encerramento_motivo',
                'livros.titulo as titulo',
            ])
            ->when($search, fn (Builder $loans, string $term) => $loans->whereLike('livros.titulo', "%{$term}%"));

        if ($population === 'period') {
            $query->selectRaw('locacoes.data_devolucao AS prazo_exibido');
        } else {
            $query->selectRaw($this->dueAtReferenceSql().' AS prazo_exibido', [$period->referenceEnd()]);
        }

        return $query
            ->orderByDesc('locacoes.data_locacao')
            ->orderByDesc('locacoes.id');
    }

    /** @param Builder<Locacao> $query
     * @return Builder<Locacao>
     */
    private function loanBookSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $loans, string $term) => $loans
            ->whereHas('livro', fn (Builder $books) => $books->whereLike('titulo', "%{$term}%")));
    }

    private function dueAtReferenceSql(): string
    {
        return <<<'SQL'
COALESCE(
    (SELECT renovacoes.previous_due_date
       FROM renovacoes
      WHERE renovacoes.locacao_id = locacoes.id
        AND renovacoes.created_at > ?
      ORDER BY renovacoes.created_at ASC, renovacoes.id ASC
      LIMIT 1),
    locacoes.data_devolucao
)
SQL;
    }
}
