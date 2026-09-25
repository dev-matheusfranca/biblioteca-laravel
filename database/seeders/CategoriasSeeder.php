<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriasSeeder extends Seeder
{
    public function run()
    {
        Categoria::insert([
            ['nome' => 'Ficcao', 'descricao' => 'Ficção em geral', 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Tecnico', 'descricao' => 'Livros técnicos', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
