<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ReservationStatus $status
 * @property array<string, mixed> $policy_snapshot
 * @property Carbon|null $disponivel_em
 * @property Carbon|null $expira_em
 * @property Carbon|null $encerrada_em
 */
class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'usuario_id',
        'livro_id',
        'status',
        'active_key',
        'exemplar_id',
        'active_exemplar_id',
        'policy_id',
        'policy_snapshot',
        'availability_version',
        'disponivel_em',
        'expira_em',
        'encerrada_em',
        'encerramento_motivo',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'policy_snapshot' => 'array',
            'availability_version' => 'integer',
            'disponivel_em' => 'datetime',
            'expira_em' => 'datetime',
            'encerrada_em' => 'datetime',
        ];
    }

    public static function activeKey(int $userId, int $bookId): string
    {
        return $userId.':'.$bookId;
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<Livro, $this> */
    public function livro(): BelongsTo
    {
        return $this->belongsTo(Livro::class, 'livro_id');
    }

    /** @return BelongsTo<Exemplar, $this> */
    public function exemplar(): BelongsTo
    {
        return $this->belongsTo(Exemplar::class);
    }

    /** @return BelongsTo<CirculationPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(CirculationPolicy::class, 'policy_id');
    }
}
