<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Autor extends Model
{
    use HasFactory;

    protected $table = 'autores';

    protected $fillable = ['nome', 'nacionalidade'];

    /** @return HasMany<Livro, $this> */
    public function livros(): HasMany
    {
        return $this->hasMany(Livro::class, 'autor_id');
    }
}
