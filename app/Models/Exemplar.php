<?php

namespace App\Models;

use App\Enums\ExemplarCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property ExemplarCondition $condicao */
class Exemplar extends Model
{
    use HasFactory;

    protected $table = 'exemplares';

    protected $fillable = ['livro_id', 'codigo_patrimonial', 'condicao', 'origem', 'identificacao_fisica', 'motivo_condicao'];

    protected function casts(): array
    {
        return ['condicao' => ExemplarCondition::class, 'identificacao_fisica' => 'boolean'];
    }

    /** @return BelongsTo<Livro, $this> */
    public function livro(): BelongsTo
    {
        return $this->belongsTo(Livro::class);
    }

    /** @return HasOne<Locacao, $this> */
    public function locacaoAtiva(): HasOne
    {
        return $this->hasOne(Locacao::class, 'active_exemplar_id');
    }

    /** @return HasOne<Reserva, $this> */
    public function reservaAtiva(): HasOne
    {
        return $this->hasOne(Reserva::class, 'active_exemplar_id');
    }

    public function isAvailable(): bool
    {
        return $this->identificacao_fisica
            && $this->condicao === ExemplarCondition::Circulation
            && ! $this->locacaoAtiva()->exists()
            && ! $this->reservaAtiva()->exists();
    }
}
