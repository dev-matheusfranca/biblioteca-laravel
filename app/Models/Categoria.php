<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = ['nome', 'descricao'];

    /** @return HasMany<Livro, $this> */
    public function livros(): HasMany
    {
        return $this->hasMany(Livro::class, 'categoria_id');
    }
}
