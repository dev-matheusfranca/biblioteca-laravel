<?php

namespace App\Services\Reports;

use App\Enums\AcervoMode;
use App\Enums\ExemplarCondition;
use App\Models\Exemplar;
use Illuminate\Database\Eloquent\Builder;

class UnavailableInventoryReport
{
    public const REASON_LABELS = [
        'nao_identificado' => 'Identificação física pendente',
        'manutencao' => 'Em manutenção',
        'extraviado' => 'Extraviado',
        'baixado' => 'Baixado',
        'emprestado' => 'Em empréstimo',
        'reservado' => 'Separado para reserva',
        'reconciliacao' => 'Título em reconciliação',
        'titulo_inativo' => 'Título inativo',
    ];

    /** @return Builder<Exemplar> */
    public function query(?string $search = null): Builder
    {
        $reasonSql = $this->reasonSql();

        return Exemplar::query()
            ->join('livros', 'livros.id', '=', 'exemplares.livro_id')
            ->select([
                'exemplares.id',
                'exemplares.codigo_patrimonial',
                'exemplares.condicao',
                'exemplares.identificacao_fisica',
                'livros.titulo as titulo',
                'livros.status as livro_status',
                'livros.modo_acervo as modo_acervo',
            ])
            ->selectRaw("{$reasonSql} AS motivo_indisponibilidade")
            ->where(function (Builder $query): void {
                $query->where('exemplares.identificacao_fisica', false)
                    ->orWhere('exemplares.condicao', '!=', ExemplarCondition::Circulation->value)
                    ->orWhereHas('locacaoAtiva')
                    ->orWhereHas('reservaAtiva')
                    ->orWhere('livros.modo_acervo', '!=', AcervoMode::Copies->value)
                    ->orWhere('livros.status', '!=', 'ativo');
            })
            ->when($search, fn (Builder $query, string $term) => $query->whereLike('livros.titulo', "%{$term}%"))
            ->orderBy('motivo_indisponibilidade')
            ->orderBy('livros.titulo')
            ->orderBy('exemplares.codigo_patrimonial')
            ->orderBy('exemplares.id');
    }

    public function reasonLabel(Exemplar $copy): string
    {
        $reason = (string) $copy->getAttribute('motivo_indisponibilidade');

        return self::REASON_LABELS[$reason] ?? 'Indisponível';
    }

    private function reasonSql(): string
    {
        return <<<'SQL'
CASE
    WHEN exemplares.identificacao_fisica = 0 THEN 'nao_identificado'
    WHEN exemplares.condicao = 'manutencao' THEN 'manutencao'
    WHEN exemplares.condicao = 'extraviado' THEN 'extraviado'
    WHEN exemplares.condicao = 'baixado' THEN 'baixado'
    WHEN EXISTS (SELECT 1 FROM locacoes WHERE locacoes.active_exemplar_id = exemplares.id) THEN 'emprestado'
    WHEN EXISTS (SELECT 1 FROM reservas WHERE reservas.active_exemplar_id = exemplares.id) THEN 'reservado'
    WHEN livros.modo_acervo <> 'exemplares' THEN 'reconciliacao'
    WHEN livros.status <> 'ativo' THEN 'titulo_inativo'
    ELSE NULL
END
SQL;
    }
}
