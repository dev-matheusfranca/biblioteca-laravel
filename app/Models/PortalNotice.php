<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalNotice extends Model
{
    use HasFactory;

    protected $fillable = ['outbox_id', 'usuario_id', 'type', 'title', 'body', 'read_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->cancelled_at === null;
    }

    /** @return BelongsTo<CommunicationOutbox, $this> */
    public function outbox(): BelongsTo
    {
        return $this->belongsTo(CommunicationOutbox::class, 'outbox_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
