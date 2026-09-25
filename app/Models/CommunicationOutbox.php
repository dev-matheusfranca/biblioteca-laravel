<?php

namespace App\Models;

use App\Enums\OutboxStatus;
use App\Enums\OutboxType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property OutboxStatus $status
 * @property OutboxType $type
 * @property array<string, mixed> $payload
 * @property Carbon|null $leased_at
 * @property Carbon|null $last_enqueued_at
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $completed_at
 */
class CommunicationOutbox extends Model
{
    use HasFactory;

    protected $table = 'communication_outbox';

    protected $fillable = [
        'event_key',
        'type',
        'usuario_id',
        'locacao_id',
        'reserva_id',
        'payload',
        'status',
        'attempts',
        'correlation_id',
        'lease_token',
        'leased_at',
        'last_enqueued_at',
        'next_attempt_at',
        'portal_completed_at',
        'mail_completed_at',
        'completed_at',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'type' => OutboxType::class,
            'status' => OutboxStatus::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'leased_at' => 'datetime',
            'last_enqueued_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'portal_completed_at' => 'datetime',
            'mail_completed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<Locacao, $this> */
    public function locacao(): BelongsTo
    {
        return $this->belongsTo(Locacao::class);
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return HasOne<PortalNotice, $this> */
    public function portalNotice(): HasOne
    {
        return $this->hasOne(PortalNotice::class, 'outbox_id');
    }
}
