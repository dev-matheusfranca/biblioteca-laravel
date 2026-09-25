<?php

namespace App\Actions\Communication;

use App\Enums\OutboxType;
use App\Enums\UserRole;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\User;
use App\Services\CorrelationId;
use App\Services\CurrentCirculationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PrepareCommunicationEvents
{
    public function __construct(
        private readonly CurrentCirculationPolicy $policies,
        private readonly CorrelationId $correlations,
        private readonly RecordOutboxEvent $outbox,
    ) {}

    public function execute(): int
    {
        $policy = $this->policies->get();
        $today = CarbonImmutable::now($policy->timezone)->startOfDay();
        $correlationId = $this->correlations->current();
        $references = Locacao::query()
            ->whereNull('encerrado_em')
            ->where(function ($query) use ($today) {
                $query->whereBetween('data_devolucao', [$today->toDateString(), $today->addDays(2)->toDateString()])
                    ->orWhereDate('data_devolucao', '<=', $today->subDay()->toDateString());
            })
            ->orderBy('id')
            ->get(['id', 'usuario_id', 'livro_id']);

        $created = 0;
        foreach ($references as $reference) {
            $created += DB::transaction(function () use ($reference, $today, $correlationId, $policy) {
                $reader = User::query()->lockForUpdate()->find($reference->usuario_id);
                Livro::query()->lockForUpdate()->find($reference->livro_id);
                $loan = Locacao::query()->lockForUpdate()->find($reference->id);
                if (! $reader || ! $loan || ! $reader->isActive() || $reader->role !== UserRole::Reader || $loan->encerrado_em) {
                    return 0;
                }
                $dueDate = CarbonImmutable::parse($loan->data_devolucao, $policy->timezone)->startOfDay();
                $type = match (true) {
                    $dueDate->betweenIncluded($today, $today->addDays(2)) => OutboxType::LoanDueSoon,
                    $dueDate->lte($today->subDay()) => OutboxType::LoanOverdue,
                    default => null,
                };
                if (! $type) {
                    return 0;
                }
                $event = $this->outbox->loanReminder($loan, $type, $dueDate->toDateString(), $correlationId);

                return $event->wasRecentlyCreated ? 1 : 0;
            }, 3);
        }

        return $created;
    }
}
