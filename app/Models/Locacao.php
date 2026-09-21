<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Locacao extends Model
{
    use HasFactory;

    protected $table = 'locacoes';

    protected $fillable = [
        'usuario_id',
        'livro_id',
        'data_locacao',
        'data_devolucao',
        'data_devolvido',
        'status',
    ];

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    public function livro()
    {
        return $this->belongsTo(Livro::class, 'livro_id');
    }

    public function getSituacaoAtualAttribute(): string
    {
        if ($this->status !== 'devolvida' && Carbon::parse($this->data_devolucao)->isBefore(today())) {
            return 'atrasada';
        }

        return $this->status;
    }
}
