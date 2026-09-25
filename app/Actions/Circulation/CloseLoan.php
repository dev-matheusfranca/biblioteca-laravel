<?php

namespace App\Actions\Circulation;

use App\Models\AuditLog;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CloseLoan
{
    public function __construct(private readonly AllocateReservationsForBook $allocator) {}

    public function return(User $actor, Locacao $loan): bool
    {
        return $this->close($actor, $loan, 'devolucao', null);
    }

    public function loss(User $actor, Locacao $loan, string $reason): bool
    {
        if (trim($reason) === '') {
            throw new DomainException('Informe o motivo da perda.');
        }

        return $this->close($actor, $loan, 'perda', $reason);
    }

    private function close(User $actor, Locacao $loan, string $reason, ?string $detail): bool
    {
        return DB::transaction(function () use ($actor, $loan, $reason, $detail) {
            $reader = User::query()->lockForUpdate()->findOrFail($loan->usuario_id);
            $book = Livro::query()->lockForUpdate()->findOrFail($loan->livro_id);

            if (! $actor->fresh()?->isStaff()) {
                throw new DomainException('Operador sem permissão para encerrar empréstimos.');
            }
            $lockedLoan = Locacao::query()->lockForUpdate()->findOrFail($loan->getKey());
            if ($lockedLoan->encerrado_em) {
                return false;
            }

            if ($reason === 'perda' && ! $lockedLoan->exemplar_id) {
                throw new DomainException('A perda só pode ser registrada depois de vincular um exemplar físico.');
            }

            if ($reason === 'perda') {
                $exemplar = $lockedLoan->exemplar()->lockForUpdate()->firstOrFail();
                $exemplar->forceFill(['condicao' => 'extraviado', 'motivo_condicao' => $detail])->save();
            }

            $lockedLoan->forceFill([
                'status' => 'devolvida',
                'data_devolvido' => $reason === 'devolucao' ? now()->toDateString() : null,
                'encerrado_em' => now(),
                'encerramento_motivo' => $reason,
                'active_exemplar_id' => null,
            ])->save();

            if (! $book->usaExemplares()) {
                $book->update(['quantidade_disponivel' => min($book->quantidade_total, $book->quantidade_disponivel + 1)]);
            } else {
                $this->allocator->executeLocked($book);
            }

            AuditLog::create(['action' => $reason === 'perda' ? 'loan.closed_for_loss' : 'loan.returned', 'actor_id' => $actor->id, 'subject_id' => $lockedLoan->usuario_id, 'metadata' => ['livro_id' => $book->id, 'exemplar_id' => $lockedLoan->exemplar_id, 'locacao_id' => $lockedLoan->id]]);

            return true;
        }, 3);
    }
}
