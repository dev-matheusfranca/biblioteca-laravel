<?php

namespace App\Models;

use App\Enums\AcervoMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property AcervoMode $modo_acervo */
class Livro extends Model
{
    use HasFactory;

    protected $table = 'livros';

    protected $fillable = [
        'titulo',
        'autor_id',
        'categoria_id',
        'ano_publicacao',
        'quantidade_total',
        'quantidade_disponivel',
        'isbn',
        'status',
        'modo_acervo',
    ];

    protected function casts(): array
    {
        return ['modo_acervo' => AcervoMode::class];
    }

    /** @return BelongsTo<Autor, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(Autor::class, 'autor_id');
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /** @return HasMany<Locacao, $this> */
    public function locacoes(): HasMany
    {
        return $this->hasMany(Locacao::class, 'livro_id');
    }

    /** @return HasMany<Exemplar, $this> */
    public function exemplares(): HasMany
    {
        return $this->hasMany(Exemplar::class);
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'livro_id');
    }

    public function usaExemplares(): bool
    {
        return $this->modo_acervo === AcervoMode::Copies;
    }
}
