<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Locacao extends Model
{
    use HasFactory;

    protected $table = 'locacoes';

    protected $fillable = [
        'usuario_id',
        'livro_id',
        'exemplar_id',
        'active_exemplar_id',
        'policy_id',
        'policy_snapshot',
        'renewal_count',
        'data_locacao',
        'data_devolucao',
        'data_devolvido',
        'encerrado_em',
        'encerramento_motivo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'encerrado_em' => 'datetime',
            'policy_snapshot' => 'array',
            'renewal_count' => 'integer',
        ];
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

    /** @return HasMany<LoanRenewal, $this> */
    public function renovacoes(): HasMany
    {
        return $this->hasMany(LoanRenewal::class)->orderBy('id');
    }

    public function getSituacaoAtualAttribute(): string
    {
        if ($this->encerramento_motivo === 'perda') {
            return 'perdida';
        }
        if ($this->status !== 'devolvida' && Carbon::parse($this->data_devolucao)->isBefore(today())) {
            return 'atrasada';
        }

        return $this->status;
    }
}
