<?php

namespace Database\Seeders;

use App\Models\Livro;
use Illuminate\Database\Seeder;

class LivrosSeeder extends Seeder
{
    public function run()
    {
        Livro::create([
            'titulo' => '1984',
            'autor_id' => 2,
            'categoria_id' => 1,
            'ano_publicacao' => 1949,
            'quantidade_total' => 5,
            'quantidade_disponivel' => 5,
            'isbn' => '978-0451524935',
        ]);

        Livro::create([
            'titulo' => 'Dom Casmurro',
            'autor_id' => 1,
            'categoria_id' => 1,
            'ano_publicacao' => 1899,
            'quantidade_total' => 3,
            'quantidade_disponivel' => 3,
            'isbn' => '978-1234567890',
        ]);
    }
}
