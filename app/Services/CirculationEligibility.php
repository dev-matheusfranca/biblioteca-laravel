<?php

namespace App\Services;

use App\Enums\ExemplarCondition;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CirculationPolicy;
use App\Models\Exemplar;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\Reserva;
use App\Models\User;
use Carbon\CarbonImmutable;

class CirculationEligibility
{
    public function checkoutBlockReason(User $reader, Livro $book, CirculationPolicy $policy): ?string
    {
        if (! $reader->isActive() || $reader->role !== UserRole::Reader) {
            return 'Leitor indisponível para empréstimo.';
        }
        if (! $book->usaExemplares()) {
            return 'Este título aguarda reconciliação do inventário e não aceita novos empréstimos.';
        }
        if ($book->status !== 'ativo') {
            return 'Livro indisponível para empréstimo.';
        }
        if (Locacao::query()->where('usuario_id', $reader->id)->where('livro_id', $book->id)->whereNull('encerrado_em')->exists()) {
            return 'O leitor já possui este título emprestado.';
        }
        if ($policy->blocks_overdue && $this->hasOverdueLoan($reader, $policy)) {
            return 'O leitor possui empréstimo em atraso e não pode realizar nova retirada.';
        }
        if ($this->openLoanCount($reader) >= $policy->max_open_loans) {
            return "O leitor atingiu o limite de {$policy->max_open_loans} empréstimos em aberto.";
        }

        return null;
    }

    public function reservationBlockReason(User $reader, Livro $book, CirculationPolicy $policy): ?string
    {
        if (! $reader->isActive() || $reader->role !== UserRole::Reader) {
            return 'Somente leitores ativos podem reservar títulos.';
        }
        if (! $book->usaExemplares() || $book->status !== 'ativo') {
            return 'Este título não está disponível para reservas.';
        }
        if (Locacao::query()->where('usuario_id', $reader->id)->where('livro_id', $book->id)->whereNull('encerrado_em')->exists()) {
            return 'Você já possui este título emprestado.';
        }
        if (Reserva::query()->where('active_key', Reserva::activeKey($reader->id, $book->id))->exists()) {
            return 'Você já possui uma reserva ativa para este título.';
        }

        return null;
    }

    public function renewalBlockReason(User $reader, Locacao $loan, CirculationPolicy $policy): ?string
    {
        if (! $reader->isActive() || $reader->role !== UserRole::Reader) {
            return 'Leitor indisponível para renovação.';
        }
        if ($loan->encerrado_em) {
            return 'Este empréstimo já foi encerrado.';
        }
        if ($loan->renewal_count >= $policy->max_renewals) {
            return "Este empréstimo já atingiu o limite de {$policy->max_renewals} renovação(ões).";
        }
        if (! $loan->exemplar_id || ! $loan->active_exemplar_id || ! Exemplar::query()
            ->whereKey($loan->active_exemplar_id)
            ->where('condicao', ExemplarCondition::Circulation->value)
            ->where('identificacao_fisica', true)
            ->exists()) {
            return 'Conclua a reconciliação do exemplar antes de renovar este empréstimo.';
        }
        if ($policy->blocks_overdue && CarbonImmutable::parse($loan->data_devolucao, $policy->timezone)->startOfDay()->isBefore($this->today($policy))) {
            return 'Empréstimos em atraso não podem ser renovados.';
        }
        if ($policy->blocks_overdue && $this->hasOverdueLoan($reader, $policy)) {
            return 'O leitor possui empréstimo em atraso e não pode renovar.';
        }
        if ($this->hasEligibleReservationWaiting($loan->livro_id, $reader->id, $policy)) {
            return 'Há uma reserva elegível aguardando este título.';
        }
        if (! CarbonImmutable::parse($loan->data_devolucao, $policy->timezone)->startOfDay()->addDays($policy->renewal_days)->isAfter($this->today($policy))) {
            return 'O novo prazo calculado pela política ainda estaria vencido.';
        }

        return null;
    }

    public function readerCanReceiveHold(User $reader, CirculationPolicy $policy): bool
    {
        return $reader->isActive()
            && $reader->role === UserRole::Reader
            && (! $policy->blocks_overdue || ! $this->hasOverdueLoan($reader, $policy))
            && $this->openLoanCount($reader) < $policy->max_open_loans;
    }

    public function openLoanCount(User $reader): int
    {
        return Locacao::query()->where('usuario_id', $reader->id)->whereNull('encerrado_em')->count();
    }

    public function hasOverdueLoan(User $reader, CirculationPolicy $policy): bool
    {
        return Locacao::query()
            ->where('usuario_id', $reader->id)
            ->whereNull('encerrado_em')
            ->whereDate('data_devolucao', '<', $this->today($policy)->toDateString())
            ->exists();
    }

    private function hasEligibleReservationWaiting(int $bookId, int $excludedReaderId, CirculationPolicy $policy): bool
    {
        $reservations = Reserva::query()
            ->where('livro_id', $bookId)
            ->whereNotNull('active_key')
            ->where('status', ReservationStatus::Waiting->value)
            ->where('usuario_id', '!=', $excludedReaderId)
            ->with('usuario')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $reservations->contains(fn (Reserva $reservation): bool => $reservation->usuario !== null
            && $this->readerCanReceiveHold($reservation->usuario, $policy));
    }

    private function today(CirculationPolicy $policy): CarbonImmutable
    {
        return CarbonImmutable::now($policy->timezone)->startOfDay();
    }
}
