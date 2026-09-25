<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRenewal extends Model
{
    use HasFactory;

    protected $table = 'renovacoes';

    protected $fillable = [
        'locacao_id',
        'actor_id',
        'policy_id',
        'previous_due_date',
        'new_due_date',
        'policy_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'previous_due_date' => 'date',
            'new_due_date' => 'date',
            'policy_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<Locacao, $this> */
    public function locacao(): BelongsTo
    {
        return $this->belongsTo(Locacao::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<CirculationPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(CirculationPolicy::class, 'policy_id');
    }
}
